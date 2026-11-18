<?php

namespace Tests\Feature;

use App\Services\HandoffTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandoffTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_renders_start_with_valid_state(): void
    {
        $token = $this->mintHandoff(['task_id' => 'OP-2026-7842']);

        $this->get('/start?t='.urlencode($token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Start')
                ->where('linkState', 'valid')
                ->where('task.reference', 'OP-2026-7842'));
    }

    public function test_malformed_token_rejected(): void
    {
        $this->get('/start?t=not.a.real.jwt.token')
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'malformed'));

        $this->get('/start')
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'malformed'));
    }

    public function test_invalid_signature_rejected(): void
    {
        $token = $this->mintHandoff();
        [$h, $p, $s] = explode('.', $token);

        // Tamper the payload without re-signing.
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        $payload['sub'] = 'vol-attacker';
        $tampered = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $this->get("/start?t=$h.$tampered.$s")
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'invalid'));
    }

    public function test_expired_token_rejected(): void
    {
        $token = $this->mintHandoff(['exp' => time() - 10]);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'expired'));
    }

    public function test_token_expiring_too_far_out_rejected(): void
    {
        $token = $this->mintHandoff(['exp' => time() + 7200]);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'expired'));
    }

    public function test_wrong_issuer_rejected(): void
    {
        $token = $this->mintHandoff(['iss' => 'not-opinionpadi']);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'invalid'));
    }

    public function test_missing_required_claim_rejected(): void
    {
        $private = file_get_contents(storage_path('app/keys/opinionpadi_private.pem'));
        $token = HandoffTokenService::mint($private, [
            'iss' => config('opinionpadi.issuer'),
            'sub' => 'vol-abc',
            'task_id' => 'OP-1',
            // no jti
            'exp' => time() + 600,
        ]);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'malformed'));
    }

    public function test_replayed_jti_rejected(): void
    {
        $jti = (string) Str::uuid();
        $this->beginSession(['jti' => $jti]); // consumes the jti

        $replayed = $this->mintHandoff(['jti' => $jti]);
        $this->get('/start?t='.urlencode($replayed))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'used'));
    }

    public function test_dev_handoff_route_mints_working_token_in_local(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/dev/handoff');
        $response->assertRedirect();

        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertArrayHasKey('t', $q);

        $this->get('/start?t='.urlencode($q['t']))
            ->assertInertia(fn (Assert $page) => $page->where('linkState', 'valid'));
    }

    public function test_session_creation_writes_audit_event(): void
    {
        $session = $this->beginSession();

        $this->assertSame('created', $session->state->value);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'session.created',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
        ]);
    }
}

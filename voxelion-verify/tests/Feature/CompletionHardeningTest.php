<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\VerificationSession;
use App\Services\HandoffTokenService;
use App\Services\VerificationProvider\VerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Provider that declares a terminal hard failure (e.g. spoof detected). */
class HardFailProvider implements VerificationProvider
{
    public function photoChecks(string $localPath): array
    {
        return [
            'face' => ['passed' => true, 'message' => 'ok'],
            'blur' => ['passed' => true, 'message' => 'ok'],
            'lighting' => ['passed' => true, 'message' => 'ok'],
        ];
    }

    public function verifyLiveness(string $challenge, string $capturePath): array
    {
        return [
            'liveness_passed' => false,
            'similarity_score' => 0.05,
            'raw' => ['provider' => 'test-hardfail', 'hard_fail' => true],
        ];
    }
}

class CompletionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function driveToLive(): VerificationSession
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);

        return $session;
    }

    // ---- P0-7: iat claim enforcement ----

    public function test_handoff_without_iat_is_malformed(): void
    {
        $private = file_get_contents(storage_path('app/keys/opinionpadi_private.pem'));
        $claims = [
            'iss' => config('opinionpadi.issuer'),
            'sub' => 'vol-1', 'task_id' => 'T1', 'jti' => 'j1',
            'exp' => time() + 600,
        ]; // no iat
        $token = HandoffTokenService::mint($private, $claims);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn ($page) => $page->where('linkState', 'malformed'));
    }

    public function test_future_iat_rejected(): void
    {
        $token = $this->mintHandoff(['iat' => time() + 600]);

        $this->get('/start?t='.urlencode($token))
            ->assertInertia(fn ($page) => $page->where('linkState', 'invalid'));
    }

    // ---- Liveness challenge endpoint ----

    public function test_challenge_endpoint_issues_challenge_and_audits(): void
    {
        $session = $this->driveToLive();

        $this->postJson('/live/challenge')
            ->assertOk()
            ->assertJsonStructure(['challenge']);

        $this->assertNotNull($session->fresh()->current_challenge);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'liveness.challenge_issued',
            'entity_id' => $session->id,
        ]);
    }

    // ---- P0-4: expiry notification ----

    public function test_expired_session_notifies_opinionpadi_and_audits(): void
    {
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $session = $this->beginSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $this->get('/consent')->assertRedirect('/exit?reason=expired');

        $this->assertSame(SessionState::Withdrawn, $session->fresh()->state);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['event'] === 'withdrawn' && ($body['data']['reason'] ?? null) === 'expired';
        });
        $this->assertDatabaseHas('audit_events', [
            'action' => 'session.expired',
            'entity_id' => $session->id,
        ]);
    }

    // ---- P0-8 + P0-5: failed photo audit + evidence ----

    public function test_photo_failure_writes_failed_audit_event(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // 50x50 noise PNG — a real image that fails size + dimensions.
        $this->post('/photo', ['photo' => $this->noiseImage(50, 50)])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseHas('audit_events', [
            'action' => 'photo.failed',
            'entity_id' => $session->id,
        ]);
        $this->assertNotNull(
            AuditEvent::where('action', 'photo.failed')->first()->metadata_json['failed_checks'] ?? null
        );
    }

    public function test_final_failed_photo_kept_as_review_evidence(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/photo', ['photo' => $this->noiseImage(50, 50)]);
        }

        $this->assertSame(SessionState::ManualReview, $session->fresh()->state);

        $evidence = MediaAsset::where('participant_id', $session->participant_id)->get();
        $this->assertCount(1, $evidence); // only the final attempt is kept
        $this->assertFalse((bool) $evidence->first()->accepted);
        $this->assertSame('photo', $evidence->first()->kind);
        Storage::disk('media')->assertExists($evidence->first()->storage_key);
    }

    // ---- P0-3 + S-5: webhook semantics + hard failure ----

    public function test_retriable_liveness_failure_sends_no_webhook(): void
    {
        $this->app->bind(VerificationProvider::class, AlwaysFailsProvider::class);
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $this->driveToLive();
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        // First failed attempt is not terminal — OpinionPadi hears nothing.
        Http::assertNothingSent();
    }

    public function test_exhausted_liveness_sends_manual_review_webhook_once(): void
    {
        $this->app->bind(VerificationProvider::class, AlwaysFailsProvider::class);
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $session = $this->driveToLive();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/live/challenge');
            $this->post('/live/complete', ['capture' => $this->noiseImage()]);
        }

        $this->assertSame(SessionState::ManualReview, $session->fresh()->state);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->body(), '"event":"manual_review"'));
    }

    public function test_hard_fail_is_terminal_failed_with_no_code(): void
    {
        $this->app->bind(VerificationProvider::class, HardFailProvider::class);
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $session = $this->driveToLive();
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()])
            ->assertRedirect('/result');

        $this->assertSame(SessionState::Failed, $session->fresh()->state);
        $this->assertDatabaseCount('completion_codes', 0);
        $this->assertDatabaseHas('verification_results', [
            'session_id' => $session->id,
            'outcome' => 'failed',
        ]);
        Http::assertSent(fn ($r) => str_contains($r->body(), '"event":"failed"'));
    }
}

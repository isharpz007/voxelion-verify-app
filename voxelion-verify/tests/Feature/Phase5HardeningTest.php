<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\MediaAsset;
use App\Models\VerificationSession;
use App\Services\VerificationProvider\VerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Provider that throws while $down is set — simulates latency spike/timeout. */
class ThrowingProvider implements VerificationProvider
{
    public bool $down = true;

    public function photoChecks(string $localPath): array
    {
        if ($this->down) {
            throw new \RuntimeException('provider unreachable');
        }

        return [
            'face' => ['passed' => true, 'message' => 'ok'],
            'blur' => ['passed' => true, 'message' => 'ok'],
            'lighting' => ['passed' => true, 'message' => 'ok'],
        ];
    }

    public function verifyLiveness(string $challenge, string $capturePath): array
    {
        if ($this->down) {
            throw new \RuntimeException('provider timeout');
        }

        return [
            'liveness_passed' => true,
            'similarity_score' => 0.92,
            'raw' => ['provider' => 'test', 'simulated' => true],
        ];
    }
}

class Phase5HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function driveToLive(): VerificationSession
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()])->assertRedirect('/live');
        $this->postJson('/live/challenge')->assertOk();

        return $session;
    }

    public function test_provider_timeout_is_retriable_and_burns_no_attempt(): void
    {
        $session = $this->driveToLive();

        $provider = new ThrowingProvider;
        $this->app->instance(VerificationProvider::class, $provider);

        $this->post('/live/complete', ['capture' => $this->noiseImage()])
            ->assertSessionHasErrors('capture');

        $session->refresh();
        $this->assertSame(SessionState::LiveCheck, $session->state);
        // The outage was not the volunteer's failure — no attempt consumed.
        $this->assertSame(0, $session->liveness_attempts);
        $this->assertDatabaseHas('audit_events', ['action' => 'provider.error']);
        $this->assertDatabaseCount('completion_codes', 0);

        // Provider recovers — the same journey retries and reaches VERIFIED.
        $provider->down = false;
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()])
            ->assertRedirect('/result');
        $this->assertSame(SessionState::Verified, $session->fresh()->state);
    }

    public function test_provider_outage_in_photo_check_burns_no_attempt(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        $this->app->instance(VerificationProvider::class, new ThrowingProvider);

        $this->post('/photo', ['photo' => $this->noiseImage()])
            ->assertSessionHasErrors('photo');

        $session->refresh();
        $this->assertSame(SessionState::IntakeDone, $session->state);
        $this->assertSame(0, $session->photo_attempts);
        $this->assertSame(0, MediaAsset::count());
        $this->assertDatabaseHas('audit_events', ['action' => 'provider.error']);
    }

    public function test_concurrent_sessions_do_not_bleed(): void
    {
        Storage::fake('media');
        $a = $this->beginSession(['sub' => 'vol-a', 'task_id' => 'OP-A']);
        $b = $this->beginSession(['sub' => 'vol-b', 'task_id' => 'OP-B']);

        // Act as A: consent + intake (drives A to intake_done).
        $this->withSession(['verification_session_id' => $a->id]);
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->put('/intake', [
            'full_name' => 'Volunteer A', 'email' => 'a@example.com',
            'phone_country_code' => '+234', 'phone' => '8012345678',
        ]);
        $this->post('/intake/verify', ['code' => session('dev_email_code')]);

        // Act as B: still at created — B's POST /photo is bounced by the
        // state gate back to /consent, never touching A's progress.
        $this->withSession(['verification_session_id' => $b->id]);
        $this->post('/photo', ['photo' => $this->noiseImage()])
            ->assertRedirect('/consent');

        $this->assertSame(SessionState::IntakeDone, $a->fresh()->state);
        $this->assertSame(SessionState::Created, $b->fresh()->state);
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_invalid_upload_leaves_no_residue(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // Simulates a corrupt/truncated upload — validation rejects it
        // before storage, so nothing is persisted anywhere.
        $garbage = UploadedFile::fake()->createWithContent('photo.exe', 'MZ-not-an-image');
        $this->post('/photo', ['photo' => $garbage])->assertSessionHasErrors('photo');

        $this->assertSame(0, MediaAsset::count());
        $this->assertSame(0, $session->fresh()->photo_attempts);
        $this->assertSame(SessionState::IntakeDone, $session->fresh()->state);
        Storage::disk('media')->assertDirectoryEmpty('media/'.$session->participant_id);
    }

    public function test_completion_code_enumeration_is_rate_limited(): void
    {
        // 60/min throttle on /api/v1 — brute-force code guessing dies at 60.
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/completion-codes/VX-GUES-GUES-GUES', $this->signedApiHeaders());
        }

        $this->getJson('/api/v1/completion-codes/VX-GUES-GUES-GUES', $this->signedApiHeaders())
            ->assertStatus(429);
    }
}

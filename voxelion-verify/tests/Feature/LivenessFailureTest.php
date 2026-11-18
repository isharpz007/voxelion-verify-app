<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\VerificationSession;
use App\Services\VerificationProvider\VerificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** A provider that always fails liveness — bound per-test to exercise the failure path. */
class AlwaysFailsProvider implements VerificationProvider
{
    public function photoChecks(string $localPath): array
    {
        $ok = @getimagesize($localPath) !== false;

        return [
            'face' => ['passed' => $ok, 'message' => 'No face detected.'],
            'blur' => ['passed' => $ok, 'message' => 'Too blurry.'],
            'lighting' => ['passed' => $ok, 'message' => 'Too dim.'],
        ];
    }

    public function verifyLiveness(string $challenge, string $capturePath): array
    {
        return [
            'liveness_passed' => false,
            'similarity_score' => 0.30,
            'raw' => ['provider' => 'test-fail', 'simulated' => true],
        ];
    }
}

class LivenessFailureTest extends TestCase
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

    public function test_liveness_failure_counts_attempt_then_allows_retry(): void
    {
        $this->app->bind(VerificationProvider::class, AlwaysFailsProvider::class);
        $session = $this->driveToLive();

        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()])
            ->assertSessionHasErrors('capture');

        $this->assertSame(SessionState::LiveCheck, $session->fresh()->state);
        $this->assertSame(1, $session->fresh()->liveness_attempts);
    }

    public function test_liveness_failure_exhaustion_leads_to_manual_review(): void
    {
        $this->app->bind(VerificationProvider::class, AlwaysFailsProvider::class);
        $session = $this->driveToLive();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/live/challenge');
            $this->post('/live/complete', ['capture' => $this->noiseImage()]);
        }

        $this->assertSame(SessionState::ManualReview, $session->fresh()->state);
        $this->assertSame(3, $session->fresh()->liveness_attempts);

        $this->assertDatabaseHas('verification_results', [
            'session_id' => $session->id,
            'outcome' => 'manual_review',
        ]);

        // Result page reflects the manual-review outcome, no code issued.
        $this->get('/result')->assertInertia(
            fn (Assert $page) => $page->where('outcome', 'manual_review')->where('code', null));
        $this->assertDatabaseCount('completion_codes', 0);
    }

    public function test_no_verified_outcome_without_provider_pass(): void
    {
        $this->app->bind(VerificationProvider::class, AlwaysFailsProvider::class);
        $session = $this->driveToLive();

        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        // A failing provider must never produce VERIFIED or a code.
        $this->assertNotSame(SessionState::Verified, $session->fresh()->state);
        $this->assertDatabaseCount('completion_codes', 0);
    }

    public function test_still_frame_fallback_goes_straight_to_manual_review(): void
    {
        $session = $this->driveToLive();

        // Accessibility path (Phase 0 decision): a still photo instead of
        // the video challenge — no liveness attempt consumed, no provider
        // call, straight to manual review with the image kept as evidence.
        $this->post('/live/complete', [
            'capture' => $this->noiseImage(),
            'still' => true,
        ])->assertRedirect('/result');

        $session->refresh();
        $this->assertSame(SessionState::ManualReview, $session->state);
        $this->assertSame(0, $session->liveness_attempts);
        $this->assertDatabaseHas('verification_results', [
            'session_id' => $session->id,
            'outcome' => 'manual_review',
            'similarity_score' => null,
        ]);
        $this->assertDatabaseHas('media_assets', [
            'participant_id' => $session->participant_id,
            'kind' => 'still',
        ]);
        $this->assertDatabaseCount('completion_codes', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'liveness.accessibility_fallback']);
    }
}

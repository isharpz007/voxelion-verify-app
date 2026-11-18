<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\CompletionCode;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JourneyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_through_to_verified_result_with_code(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // Photo upload
        $this->post('/photo', ['photo' => $this->noiseImage()])
            ->assertRedirect('/live');
        $this->assertSame(SessionState::PhotoOk, $session->fresh()->state);
        $this->assertSame(1, MediaAsset::where('kind', 'photo')->count());

        // Liveness challenge + capture
        $this->postJson('/live/challenge')->assertOk()->assertJsonStructure(['challenge']);
        $this->post('/live/complete', ['capture' => $this->noiseImage()])
            ->assertRedirect('/result');

        $session->refresh();
        $this->assertSame(SessionState::Verified, $session->state);
        $this->assertDatabaseHas('verification_results', [
            'session_id' => $session->id,
            'outcome' => 'verified',
        ]);

        // Result page shows the one-time plaintext code.
        $this->get('/result')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Result')
            ->where('outcome', 'verified')
            ->where('task.reference', 'OP-2026-7842')
            ->has('code')
            ->whereNot('code', null));

        $code = CompletionCode::firstOrFail();
        $this->assertNull($code->consumed_at);
        $this->assertTrue($code->expires_at->isFuture());
        // Only the HMAC hash is at rest.
        $this->assertNotEmpty($code->code_hash);
    }

    public function test_failing_photo_three_times_sends_to_manual_review(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // A real but under-spec PNG: passes request validation (valid image)
        // yet fails the quality gate (below 480px min + below 10KB min).
        for ($i = 0; $i < 3; $i++) {
            $this->post('/photo', ['photo' => $this->noiseImage(50, 50)]);
        }

        $this->assertSame(SessionState::ManualReview, $session->fresh()->state);
        $this->get('/result')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('outcome', 'manual_review'));
    }

    public function test_withdrawal_scrubs_pii_and_deletes_media(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);

        $participant = $session->participant;
        $this->assertSame(1, MediaAsset::where('participant_id', $participant->id)->count());

        $this->post('/withdraw')->assertRedirect();
        $this->assertSame(SessionState::Withdrawn, $session->fresh()->state);

        // Sync queue ran the deletion job.
        $participant->refresh();
        $this->assertSame('deleted', $participant->status);
        $this->assertNull($participant->full_name);
        $this->assertNull($participant->email);
        $this->assertSame(0, MediaAsset::where('participant_id', $participant->id)->count());
        $this->assertDatabaseHas('deletion_requests', [
            'participant_id' => $participant->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'deletion.completed']);
    }
}

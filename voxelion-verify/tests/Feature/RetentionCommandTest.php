<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\CompletionCode;
use App\Models\MediaAsset;
use App\Models\Participant;
use App\Models\RewardSubmission;
use App\Models\VerificationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    private function oldSession(SessionState $state, ?Participant $participant = null): VerificationSession
    {
        return VerificationSession::create([
            'participant_id' => ($participant ?? Participant::create([
                'op_volunteer_ref' => 'vol-'.Str::random(6),
                'task_id' => 'T-'.Str::random(4),
            ]))->id,
            'state' => $state,
            'handoff_jti' => (string) Str::uuid(),
            'expires_at' => now()->subDays(10),
        ]);
    }

    private function setWindow(): void
    {
        config(['verification.retention.expired_session_days' => 7]);
    }

    public function test_old_non_terminal_session_is_pruned(): void
    {
        $this->setWindow();
        $session = $this->oldSession(SessionState::Consented);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseMissing('verification_sessions', ['id' => $session->id]);
    }

    public function test_manual_review_session_is_never_pruned(): void
    {
        $this->setWindow();
        $session = $this->oldSession(SessionState::ManualReview);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseHas('verification_sessions', ['id' => $session->id]);
    }

    public function test_verified_session_with_live_code_is_protected(): void
    {
        $this->setWindow();
        $session = $this->oldSession(SessionState::Verified);
        CompletionCode::create([
            'code_hash' => hash('sha256', 'x'),
            'session_id' => $session->id,
            'participant_id' => $session->participant_id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24), // still live
        ]);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseHas('verification_sessions', ['id' => $session->id]);
    }

    public function test_verified_session_with_dead_code_is_pruned(): void
    {
        $this->setWindow();
        $session = $this->oldSession(SessionState::Verified);
        CompletionCode::create([
            'code_hash' => hash('sha256', 'y'),
            'session_id' => $session->id,
            'participant_id' => $session->participant_id,
            'issued_at' => now()->subDays(4),
            'expires_at' => now()->subHours(1), // expired
        ]);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseMissing('verification_sessions', ['id' => $session->id]);
    }

    public function test_session_with_pending_reward_submission_is_protected(): void
    {
        $this->setWindow();
        $session = $this->oldSession(SessionState::Verified);
        $code = CompletionCode::create([
            'code_hash' => hash('sha256', 'z'),
            'session_id' => $session->id,
            'participant_id' => $session->participant_id,
            'issued_at' => now()->subDays(4),
            'expires_at' => now()->subHours(1), // expired code
        ]);
        RewardSubmission::create([
            'task_id' => 'T-pending',
            'participant_id' => $session->participant_id,
            'code_id' => $code->id,
            'review_state' => 'pending',
        ]);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseHas('verification_sessions', ['id' => $session->id]);
    }

    public function test_orphaned_media_files_are_removed(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('media/p1/orphan.png', 'data');
        Storage::disk('media')->put('media/p1/known.png', 'data');

        $participant = Participant::create(['op_volunteer_ref' => 'vol-x', 'task_id' => 'T-x']);
        MediaAsset::create([
            'participant_id' => $participant->id,
            'kind' => 'photo',
            'storage_key' => 'media/p1/known.png',
        ]);

        $this->artisan('voxelion:prune')->assertSuccessful();

        Storage::disk('media')->assertMissing('media/p1/orphan.png');
        Storage::disk('media')->assertExists('media/p1/known.png');
    }

    public function test_unset_windows_skip_all_pruning(): void
    {
        config(['verification.retention.expired_session_days' => null]);
        $session = $this->oldSession(SessionState::Consented);

        $this->artisan('voxelion:prune')->assertSuccessful();
        $this->assertDatabaseHas('verification_sessions', ['id' => $session->id]);
    }
}

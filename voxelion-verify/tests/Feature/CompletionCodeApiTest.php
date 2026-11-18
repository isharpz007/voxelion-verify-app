<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\CompletionCode;
use App\Models\RewardSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CompletionCodeApiTest extends TestCase
{
    use RefreshDatabase;

    /** Drive a session all the way to verified and return its plaintext code. */
    private function verifiedCode(): string
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);
        $this->assertSame(SessionState::Verified, $session->fresh()->state);

        return session('completion_code_plain');
    }

    public function test_valid_code_validates(): void
    {
        $plain = $this->verifiedCode();

        $this->getJson("/api/v1/completion-codes/$plain", $this->signedApiHeaders())
            ->assertOk()
            ->assertJson(['valid' => true, 'task_id' => 'OP-2026-7842'])
            ->assertJsonStructure(['participant_ref', 'verified_at', 'expires_at']);
    }

    public function test_unknown_code_returns_not_found(): void
    {
        $this->getJson('/api/v1/completion-codes/VX-XXXX-XXXX-XXXX', $this->signedApiHeaders())
            ->assertOk()
            ->assertJson(['valid' => false, 'reason' => 'not_found']);
    }

    public function test_code_hash_is_not_queryable_by_plaintext(): void
    {
        $this->verifiedCode();
        $code = CompletionCode::firstOrFail();

        $this->assertSame(64, strlen($code->code_hash)); // HMAC-SHA256, never plaintext
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v1/completion-codes/VX-AAAA-BBBB-CCCC')
            ->assertUnauthorized();
    }

    public function test_wrong_signature_rejected(): void
    {
        $this->getJson('/api/v1/completion-codes/VX-AAAA-BBBB-CCCC', [
            'X-OpinionPadi-Timestamp' => (string) time(),
            'X-OpinionPadi-Signature' => hash_hmac('sha256', 'bad', 'bad'),
        ])->assertUnauthorized();
    }

    public function test_stale_timestamp_rejected(): void
    {
        $ts = (string) (time() - 600); // outside the 5-minute window

        $this->getJson('/api/v1/completion-codes/VX-AAAA-BBBB-CCCC', [
            'X-OpinionPadi-Timestamp' => $ts,
            'X-OpinionPadi-Signature' => hash_hmac('sha256', $ts.'.', (string) config('opinionpadi.api_secret')),
        ])->assertUnauthorized();
    }

    public function test_expired_code_rejected(): void
    {
        $plain = $this->verifiedCode();
        CompletionCode::firstOrFail()->update(['expires_at' => now()->subHour()]);

        $this->getJson("/api/v1/completion-codes/$plain", $this->signedApiHeaders())
            ->assertJson(['valid' => false, 'reason' => 'expired']);
    }

    public function test_code_is_single_use_after_reward_approval(): void
    {
        $plain = $this->verifiedCode();

        // Submit reward → approve → code consumed.
        $res = $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))
            ->assertCreated();
        $submissionId = $res->json('submission.id');

        $this->postJson("/api/v1/reward-submissions/$submissionId/decision", [
            'decision' => 'approved',
            'reviewer_id' => 'rev-1',
        ], $this->signedApiHeaders(['decision' => 'approved', 'reviewer_id' => 'rev-1']))->assertOk();

        // Code now validates as consumed — cannot be redeemed again.
        $this->getJson("/api/v1/completion-codes/$plain", $this->signedApiHeaders())
            ->assertJson(['valid' => false, 'reason' => 'consumed']);

        // A second reward submission for the same code is rejected.
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))
            ->assertStatus(422);
    }

    public function test_duplicate_reward_submission_conflicts(): void
    {
        $plain = $this->verifiedCode();

        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))->assertCreated();
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'A reward submission already exists for this code.');
    }

    public function test_reward_submission_rejects_invalid_code(): void
    {
        $this->postJson('/api/v1/reward-submissions', ['code' => 'VX-FAKE-FAKE-FAKE'], $this->signedApiHeaders(['code' => 'VX-FAKE-FAKE-FAKE']))
            ->assertStatus(422)
            ->assertJson(['valid' => false, 'reason' => 'not_found']);
    }

    public function test_submission_list_omits_contact_pii(): void
    {
        $plain = $this->verifiedCode();
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))->assertCreated();

        $response = $this->getJson('/api/v1/reward-submissions', $this->signedApiHeaders())->assertOk();

        $row = $response->json('data.0');
        $this->assertArrayHasKey('participant_ref', $row);
        $this->assertArrayHasKey('task_id', $row);

        // Contact PII must never leave this API.
        foreach (['full_name', 'email', 'phone', 'phone_country_code', 'participant'] as $key) {
            $this->assertArrayNotHasKey($key, $row);
        }
        $this->assertStringNotContainsString('volunteer@example.com', $response->getContent());
    }

    public function test_rejected_decision_does_not_consume_code(): void
    {
        $plain = $this->verifiedCode();
        $id = $this->postJson('/api/v1/reward-submissions', ['code' => $plain], $this->signedApiHeaders(['code' => $plain]))
            ->json('submission.id');

        $this->postJson("/api/v1/reward-submissions/$id/decision", [
            'decision' => 'rejected',
            'reviewer_id' => 'rev-1',
            'reason' => 'Name mismatch',
        ], $this->signedApiHeaders(['decision' => 'rejected', 'reviewer_id' => 'rev-1', 'reason' => 'Name mismatch']))->assertOk();

        // Still valid — not consumed by a rejection.
        $this->getJson("/api/v1/completion-codes/$plain", $this->signedApiHeaders())
            ->assertJson(['valid' => true]);
    }

    public function test_screenshot_upload_is_stored_as_viewable_proof(): void
    {
        $plain = $this->verifiedCode();

        // call() with parameters+files synthesises a multipart request but
        // leaves the raw body empty — which is exactly what the signature
        // middleware hashes, so sign "".
        $res = $this->call(
            'POST',
            '/api/v1/reward-submissions',
            ['code' => $plain],
            [],
            ['screenshot' => $this->noiseImage()],
            $this->transformHeadersToServerVars($this->signedApiHeaders('')),
        );

        $res->assertCreated();

        $participant = CompletionCode::firstOrFail()->participant_id;
        $this->assertDatabaseHas('media_assets', [
            'participant_id' => $participant,
            'kind' => 'proof',
        ]);
        $key = $res->json('submission.screenshot_s3_key');
        $this->assertStringStartsWith('media/', $key);
        Storage::disk('media')->assertExists($key);
    }

    public function test_screenshot_only_submission_lands_in_queue_but_cannot_be_approved(): void
    {
        // Exit criterion made concrete: screenshot proof without a code is
        // accepted into the queue but every approval path re-validates a
        // code — screenshot alone is never sufficient.
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $ref = $session->participant->op_volunteer_ref;

        $payload = ['participant_ref' => $ref, 'screenshot_s3_key' => 'external/proof-1.png'];
        $id = $this->postJson('/api/v1/reward-submissions', $payload, $this->signedApiHeaders($payload))
            ->assertCreated()
            ->json('submission.id');

        $this->assertDatabaseHas('reward_submissions', [
            'id' => $id,
            'code_id' => null,
            'review_state' => 'pending',
        ]);

        $approve = ['decision' => 'approved', 'reviewer_id' => 'rev-1'];
        $this->postJson("/api/v1/reward-submissions/$id/decision", $approve, $this->signedApiHeaders($approve))
            ->assertStatus(422);

        $this->assertSame('pending', RewardSubmission::find($id)->review_state);
    }

    public function test_unknown_participant_ref_rejected(): void
    {
        $payload = ['participant_ref' => 'vol-ghost'];
        $this->postJson('/api/v1/reward-submissions', $payload, $this->signedApiHeaders($payload))
            ->assertStatus(404);
    }

    public function test_result_page_shows_reward_status_to_volunteer(): void
    {
        $plain = $this->verifiedCode();

        $payload = ['code' => $plain];
        $id = $this->postJson('/api/v1/reward-submissions', $payload, $this->signedApiHeaders($payload))
            ->assertCreated()->json('submission.id');

        $this->get('/result')->assertInertia(
            fn (AssertableInertia $page) => $page->where('reward.state', 'pending'));

        $approve = ['decision' => 'approved', 'reviewer_id' => 'rev-1'];
        $this->postJson("/api/v1/reward-submissions/$id/decision", $approve, $this->signedApiHeaders($approve))->assertOk();

        $this->get('/result')->assertInertia(
            fn (AssertableInertia $page) => $page->where('reward.state', 'approved'));
    }
}

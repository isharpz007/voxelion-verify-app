<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\CompletionCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletionCodeApiTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH = ['Authorization' => 'Bearer op-dev-token', 'Accept' => 'application/json'];

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

        $this->getJson("/api/v1/completion-codes/$plain", self::AUTH)
            ->assertOk()
            ->assertJson(['valid' => true, 'task_id' => 'OP-2026-7842'])
            ->assertJsonStructure(['participant_ref', 'verified_at', 'expires_at']);
    }

    public function test_unknown_code_returns_not_found(): void
    {
        $this->getJson('/api/v1/completion-codes/VX-XXXX-XXXX-XXXX', self::AUTH)
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

    public function test_wrong_bearer_token_rejected(): void
    {
        $this->getJson('/api/v1/completion-codes/VX-AAAA-BBBB-CCCC', [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();
    }

    public function test_expired_code_rejected(): void
    {
        $plain = $this->verifiedCode();
        CompletionCode::firstOrFail()->update(['expires_at' => now()->subHour()]);

        $this->getJson("/api/v1/completion-codes/$plain", self::AUTH)
            ->assertJson(['valid' => false, 'reason' => 'expired']);
    }

    public function test_code_is_single_use_after_reward_approval(): void
    {
        $plain = $this->verifiedCode();

        // Submit reward → approve → code consumed.
        $res = $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)
            ->assertCreated();
        $submissionId = $res->json('submission.id');

        $this->postJson("/api/v1/reward-submissions/$submissionId/decision", [
            'decision' => 'approved',
            'reviewer_id' => 'rev-1',
        ], self::AUTH)->assertOk();

        // Code now validates as consumed — cannot be redeemed again.
        $this->getJson("/api/v1/completion-codes/$plain", self::AUTH)
            ->assertJson(['valid' => false, 'reason' => 'consumed']);

        // A second reward submission for the same code is rejected.
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)
            ->assertStatus(422);
    }

    public function test_duplicate_reward_submission_conflicts(): void
    {
        $plain = $this->verifiedCode();

        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)->assertCreated();
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A reward submission already exists for this code.');
    }

    public function test_reward_submission_rejects_invalid_code(): void
    {
        $this->postJson('/api/v1/reward-submissions', ['code' => 'VX-FAKE-FAKE-FAKE'], self::AUTH)
            ->assertStatus(422)
            ->assertJson(['valid' => false, 'reason' => 'not_found']);
    }

    public function test_submission_list_omits_contact_pii(): void
    {
        $plain = $this->verifiedCode();
        $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)->assertCreated();

        $response = $this->getJson('/api/v1/reward-submissions', self::AUTH)->assertOk();

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
        $id = $this->postJson('/api/v1/reward-submissions', ['code' => $plain], self::AUTH)
            ->json('submission.id');

        $this->postJson("/api/v1/reward-submissions/$id/decision", [
            'decision' => 'rejected',
            'reviewer_id' => 'rev-1',
            'reason' => 'Name mismatch',
        ], self::AUTH)->assertOk();

        // Still valid — not consumed by a rejection.
        $this->getJson("/api/v1/completion-codes/$plain", self::AUTH)
            ->assertJson(['valid' => true]);
    }
}

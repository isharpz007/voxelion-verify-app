<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH = ['PHP_AUTH_USER' => 'reviewer', 'PHP_AUTH_PW' => 'review-dev-pass'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.username' => 'reviewer', 'admin.password' => 'review-dev-pass']);
    }

    public function test_review_queue_requires_credentials(): void
    {
        $this->get('/admin/review')->assertUnauthorized();
    }

    public function test_wrong_credentials_rejected(): void
    {
        $this->get('/admin/review', ['PHP_AUTH_USER' => 'reviewer', 'PHP_AUTH_PW' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_correct_credentials_grant_access(): void
    {
        $this->get('/admin/review', self::AUTH)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/ReviewQueue'));
    }

    public function test_metrics_endpoint_requires_auth_and_returns_data(): void
    {
        $this->get('/admin/metrics')->assertUnauthorized();

        $this->get('/admin/metrics', self::AUTH)
            ->assertOk()
            ->assertJsonStructure([
                'sessions_by_state', 'verification_outcomes', 'codes',
                'reward_submissions', 'deletion_requests', 'audit_events', 'generated_at',
            ]);
    }

    public function test_unconfigured_admin_credentials_fail_closed(): void
    {
        config(['admin.username' => '', 'admin.password' => '']);

        $this->get('/admin/review', ['PHP_AUTH_USER' => 'x', 'PHP_AUTH_PW' => 'y'])
            ->assertUnauthorized();
    }

    public function test_reviewer_id_comes_from_basic_auth_user(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        $plain = session('completion_code_plain');
        $id = $this->postJson('/api/v1/reward-submissions', ['code' => $plain],
            $this->signedApiHeaders(['code' => $plain]))->json('submission.id');

        $this->post("/admin/review/$id", ['decision' => 'approved'], self::AUTH);

        $this->assertDatabaseHas('reward_submissions', [
            'id' => $id,
            'review_state' => 'approved',
            'reviewer_id' => 'reviewer',
        ]);
    }

    public function test_signed_media_url_serves_private_file_to_reviewer(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('media/p1/photo-test.png', 'png-bytes');

        $asset = MediaAsset::create([
            'participant_id' => $this->beginSession()->participant_id,
            'kind' => 'photo',
            'storage_key' => 'media/p1/photo-test.png',
        ]);

        $url = URL::temporarySignedRoute('admin.media', now()->addMinutes(15), ['asset' => $asset->id]);

        // No auth → 401; valid auth + signature → file contents.
        $this->get($url)->assertUnauthorized();
        $this->get($url, self::AUTH)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unsigned_media_url_rejected(): void
    {
        Storage::fake('media');
        $asset = MediaAsset::create([
            'participant_id' => $this->beginSession()->participant_id,
            'kind' => 'photo',
            'storage_key' => 'media/p1/x.png',
        ]);

        $this->get("/admin/media/{$asset->id}", self::AUTH)->assertForbidden();
    }

    public function test_expired_signed_media_url_rejected(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('media/p1/old.png', 'png-bytes');
        $asset = MediaAsset::create([
            'participant_id' => $this->beginSession()->participant_id,
            'kind' => 'photo',
            'storage_key' => 'media/p1/old.png',
        ]);

        // Signature was valid but its window has elapsed.
        $url = URL::temporarySignedRoute('admin.media', now()->subMinute(), ['asset' => $asset->id]);

        $this->get($url, self::AUTH)->assertForbidden();
    }

    public function test_review_queue_returns_paginated_submissions(): void
    {
        $this->get('/admin/review', self::AUTH)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ReviewQueue')
                ->has('submissions.data')
                ->has('submissions.links')
                ->has('pendingCount'));
    }

    public function test_decide_on_already_decided_submission_rejected(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        $plain = session('completion_code_plain');
        $id = $this->postJson('/api/v1/reward-submissions', ['code' => $plain],
            $this->signedApiHeaders(['code' => $plain]))->json('submission.id');

        $this->post("/admin/review/$id", ['decision' => 'approved'], self::AUTH);
        $this->post("/admin/review/$id", ['decision' => 'approved'], self::AUTH)
            ->assertSessionHasErrors('decision');
    }
}

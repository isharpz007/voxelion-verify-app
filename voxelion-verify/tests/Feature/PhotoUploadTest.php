<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_url_returns_not_presigned_on_unserving_local_disk(): void
    {
        // No Storage::fake — the real local disk has serve=false, so
        // providesTemporaryUploadUrls() is false and clients must fall
        // back to multipart POST /photo.
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        $this->postJson('/photo/upload-url', ['extension' => 'jpg'])
            ->assertOk()
            ->assertJson(['presigned' => false]);
    }

    public function test_upload_url_issues_presigned_put_when_supported(): void
    {
        // Storage::fake registers a temporaryUploadUrl callback, standing
        // in for an S3 disk's real presign — verifies the response shape
        // the browser needs (PUT url + headers + key to echo back).
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        $res = $this->postJson('/photo/upload-url', ['extension' => 'png'])
            ->assertOk()
            ->assertJson(['presigned' => true])
            ->assertJsonStructure(['url', 'headers', 'key']);

        $this->assertStringStartsWith(
            'media/'.$session->participant_id.'/photo-', $res->json('key'));
    }

    public function test_upload_url_rejects_bad_extension(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        $this->postJson('/photo/upload-url', ['extension' => 'exe'])->assertStatus(422);
    }

    public function test_storage_key_path_runs_quality_checks_and_accepts(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // Simulate a browser that already PUT the object via a presigned URL.
        $key = 'media/'.$session->participant_id.'/photo-test.png';
        Storage::disk('media')->put($key, file_get_contents($this->noiseImage()->getRealPath()));

        $this->post('/photo', ['storage_key' => $key])->assertRedirect('/live');

        $this->assertSame(SessionState::PhotoOk, $session->fresh()->state);
        $this->assertDatabaseHas('media_assets', [
            'participant_id' => $session->participant_id,
            'storage_key' => $key,
            'accepted' => true,
        ]);
    }

    public function test_storage_key_path_rejects_bad_upload(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // Under-spec image fails the quality gate via the key path too.
        $key = 'media/'.$session->participant_id.'/photo-small.png';
        Storage::disk('media')->put($key, file_get_contents($this->noiseImage(50, 50)->getRealPath()));

        $this->post('/photo', ['storage_key' => $key])
            ->assertSessionHasErrors('photo');
        $this->assertSame(SessionState::IntakeDone, $session->fresh()->state);
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_storage_key_outside_participant_prefix_rejected(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // A key belonging to a different participant's prefix is refused.
        $foreign = 'media/someone-else/photo-x.png';
        Storage::disk('media')->put($foreign, file_get_contents($this->noiseImage()->getRealPath()));

        $this->post('/photo', ['storage_key' => $foreign])
            ->assertSessionHasErrors('photo');

        $this->post('/photo', ['storage_key' => 'media/'.$session->participant_id.'/photo-ghost.png'])
            ->assertSessionHasErrors('photo');
    }
}

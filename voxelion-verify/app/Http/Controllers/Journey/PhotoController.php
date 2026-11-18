<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoUploadRequest;
use App\Jobs\RunQualityChecks;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PhotoController extends Controller
{
    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');

        return Inertia::render('Photo', [
            'task' => ['reference' => $session->participant->task_id],
            'attemptsUsed' => $session->photo_attempts,
            'maxAttempts' => (int) config('verification.photo.max_retries'),
        ]);
    }

    /**
     * POST /photo/upload-url — S3 temporary-URL upload flow. When the media
     * disk supports presigned PUTs (S3 in prod) the browser uploads straight
     * to storage, then submits the returned key to POST /photo. On disks
     * without presign support (local dev) the client falls back to multipart.
     */
    public function uploadUrl(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $disk = Storage::disk('media');

        if (! $disk->providesTemporaryUploadUrls()) {
            return response()->json(['presigned' => false]);
        }

        $ext = strtolower((string) $request->input('extension', 'jpg'));
        abort_unless(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true), 422);

        $key = 'media/'.$session->participant_id.'/photo-'.Str::uuid().'.'.$ext;

        $upload = $disk->temporaryUploadUrl(
            $key,
            now()->addMinutes(10),
            ['ContentType' => 'image/'.($ext === 'jpg' ? 'jpeg' : $ext)]
        );

        return response()->json([
            'presigned' => true,
            'url' => $upload['url'],
            'headers' => $upload['headers'] ?? [],
            'key' => $key,
        ]);
    }

    public function store(PhotoUploadRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $max = (int) config('verification.photo.max_retries');
        $disk = Storage::disk('media');

        if ($session->photo_attempts >= $max) {
            return back()->withErrors(['photo' => 'Maximum attempts reached. Your verification has been sent for manual review.']);
        }

        if ($request->filled('storage_key')) {
            // Presigned flow — the file is already on the disk. Only accept
            // keys under this participant's own prefix.
            $key = (string) $request->input('storage_key');
            $prefix = 'media/'.$session->participant_id.'/photo-';

            if (! str_starts_with($key, $prefix) || ! $disk->exists($key)) {
                return back()->withErrors(['photo' => 'Upload not found — please try again.']);
            }
        } else {
            $key = 'media/'.$session->participant_id.'/photo-'.Str::uuid().'.'.$request->file('photo')->extension();
            $disk->put($key, file_get_contents($request->file('photo')->getRealPath()));
        }

        $asset = MediaAsset::create([
            'participant_id' => $session->participant_id,
            'kind' => 'photo',
            'accepted' => false,
            'storage_key' => $key,
        ]);

        // Synchronous dispatch — the page needs the verdict in this request.
        // The job writes its checks back onto the asset.
        RunQualityChecks::dispatchSync($asset->id);
        $asset->refresh();

        if (! $asset->accepted) {
            $failedChecks = collect($asset->quality_check_json)->reject(fn ($c) => $c['passed']);

            // A provider outage is not the volunteer's failure — free the
            // attempt and let them retry when the service is back.
            if ($failedChecks->keys()->all() === ['provider']) {
                $disk->delete($key);
                $asset->delete();
                AuditRecorded::dispatch('system', 'provider.error', 'verification_session', $session->id, ['stage' => 'photo_quality']);

                return back()->withErrors(['photo' => $failedChecks->first()['message']]);
            }
        }

        $attempts = $session->photo_attempts + 1;
        $session->update(['photo_attempts' => $attempts]);

        if (! $asset->accepted) {

            AuditRecorded::dispatch('participant', 'photo.failed', 'verification_session', $session->id, ['attempt' => $attempts, 'failed_checks' => $failedChecks->keys()->all()]);

            if ($attempts >= $max) {
                // Keep the final rejected capture as reviewer evidence —
                // a manual_review session would otherwise have no media.
                $session->update(['state' => SessionState::ManualReview]);
            } else {
                // Non-final rejections are not retained.
                $disk->delete($key);
                $asset->delete();
            }

            return back()->withErrors(['photo' => $failedChecks->pluck('message')->first()]);
        }

        $session->update(['state' => SessionState::PhotoOk]);

        AuditRecorded::dispatch('participant', 'photo.accepted', 'verification_session', $session->id);

        return redirect('/live');
    }
}

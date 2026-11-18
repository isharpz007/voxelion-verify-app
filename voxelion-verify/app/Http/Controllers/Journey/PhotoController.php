<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoUploadRequest;
use App\Jobs\RunQualityChecks;
use App\Models\AuditEvent;
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

    public function store(PhotoUploadRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $max = (int) config('verification.photo.max_retries');

        if ($session->photo_attempts >= $max) {
            return back()->withErrors(['photo' => 'Maximum attempts reached. Your verification has been sent for manual review.']);
        }

        $key = 'media/'.$session->participant_id.'/photo-'.Str::uuid().'.'.$request->file('photo')->extension();
        Storage::disk('media')->put($key, file_get_contents($request->file('photo')->getRealPath()));

        $asset = MediaAsset::create([
            'participant_id' => $session->participant_id,
            'kind' => 'photo',
            'accepted' => false,
            'storage_key' => $key,
        ]);

        // Synchronous dispatch — the page needs the verdict in this request.
        $assessment = RunQualityChecks::dispatchSync($asset->id);

        $attempts = $session->photo_attempts + 1;
        $session->update(['photo_attempts' => $attempts]);

        if (! $assessment['passed']) {
            $failedChecks = collect($assessment['checks'])->reject(fn ($c) => $c['passed']);

            AuditEvent::create([
                'actor' => 'participant',
                'action' => 'photo.failed',
                'entity_type' => 'verification_session',
                'entity_id' => $session->id,
                'metadata_json' => ['attempt' => $attempts, 'failed_checks' => $failedChecks->keys()->all()],
            ]);

            if ($attempts >= $max) {
                // Keep the final rejected capture as reviewer evidence —
                // a manual_review session would otherwise have no media.
                $session->update(['state' => SessionState::ManualReview]);
            } else {
                // Non-final rejections are not retained.
                Storage::disk('media')->delete($key);
                $asset->delete();
            }

            return back()->withErrors(['photo' => $failedChecks->pluck('message')->first()]);
        }

        $session->update(['state' => SessionState::PhotoOk]);

        AuditEvent::create([
            'actor' => 'participant',
            'action' => 'photo.accepted',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
        ]);

        return redirect('/live');
    }
}

<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\PhotoQualityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the photo quality gate (format, size, dimensions, face, blur,
 * lighting) against a stored media asset and records the outcome on it.
 *
 * Dispatched synchronously today — the photo step needs the verdict in the
 * same request to render pass/fail feedback. Marking it ShouldQueue keeps
 * the door open to a queued reassessment (e.g. stricter thresholds later)
 * without changing the call site.
 */
class RunQualityChecks implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $mediaAssetId) {}

    public function handle(PhotoQualityService $quality): void
    {
        $asset = MediaAsset::findOrFail($this->mediaAssetId);
        $disk = Storage::disk('media');

        // path() only exists on local disks — on S3 stream the object to a
        // temp file so the quality gate works on either driver.
        $path = $disk->path($asset->storage_key);
        if (! is_file($path)) {
            $path = tempnam(sys_get_temp_dir(), 'vox-qc-');
            file_put_contents($path, $disk->get($asset->storage_key));
        }

        try {
            $assessment = $quality->assessStoredFile(
                $path,
                (string) $disk->mimeType($asset->storage_key),
                (int) $disk->size($asset->storage_key),
            );
        } catch (\Throwable $e) {
            // Provider outage degrades to a failed check rather than a job
            // crash — the volunteer can retry the upload.
            report($e);
            $assessment = [
                'passed' => false,
                'checks' => ['provider' => ['passed' => false, 'message' => 'Verification service is temporarily unavailable — please try again.']],
            ];
        } finally {
            if (str_starts_with($path, sys_get_temp_dir())) {
                @unlink($path);
            }
        }

        $asset->update([
            'quality_check_json' => $assessment['checks'],
            'accepted' => $assessment['passed'],
        ]);
    }
}

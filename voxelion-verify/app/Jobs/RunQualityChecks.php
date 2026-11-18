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

    /** @return array{passed:bool, checks:array<string,array{passed:bool,message:string}>} */
    public function handle(PhotoQualityService $quality): array
    {
        $asset = MediaAsset::findOrFail($this->mediaAssetId);
        $disk = Storage::disk('media');

        $assessment = $quality->assessStoredFile(
            $disk->path($asset->storage_key),
            (string) $disk->mimeType($asset->storage_key),
            (int) $disk->size($asset->storage_key),
        );

        $asset->update([
            'quality_check_json' => $assessment['checks'],
            'accepted' => $assessment['passed'],
        ]);

        return $assessment;
    }
}

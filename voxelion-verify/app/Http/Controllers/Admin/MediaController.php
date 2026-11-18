<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * Streams private media objects to authenticated reviewers via short-lived
 * signed URLs. This is the local analogue of the S3 temporary-URL flow —
 * the storage disk swaps to S3 in production with no code change here.
 */
class MediaController extends Controller
{
    public function __invoke(MediaAsset $asset)
    {
        abort_unless(Storage::disk('media')->exists($asset->storage_key), 404);

        return Storage::disk('media')->response($asset->storage_key, null, [
            'Cache-Control' => 'private, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

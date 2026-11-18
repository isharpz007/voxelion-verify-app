<?php

namespace App\Http\Controllers;

use App\Services\HandoffTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Local-development helper that plays the role of OpinionPadi: it signs a
 * one-time RS256 hand-off JWT with the dev private key and redirects to
 * /start. Registered only when APP_ENV=local.
 */
class DevHandoffController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(app()->environment('local'), 404);

        $keyPath = storage_path('app/keys/opinionpadi_private.pem');
        $privateKey = is_file($keyPath) ? file_get_contents($keyPath) : null;
        abort_if(empty($privateKey) || openssl_pkey_get_private($privateKey) === false, 500, 'Dev key missing. Run: php artisan voxelion:keys');

        $token = HandoffTokenService::mint($privateKey, [
            'iss' => config('opinionpadi.issuer'),
            'sub' => $request->query('volunteer', 'vol-'.Str::random(8)),
            'task_id' => $request->query('task', 'OP-2026-7842'),
            'submission_id' => 'sub_'.Str::random(6),
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 600,
        ]);

        return redirect('/start?t='.urlencode($token));
    }
}

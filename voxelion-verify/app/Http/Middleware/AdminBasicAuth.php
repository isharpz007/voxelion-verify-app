<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * HTTP Basic auth for the reviewer screens. Credentials come from config
 * (ADMIN_USERNAME / ADMIN_PASSWORD env vars) — never hardcoded. Fails
 * closed: if credentials are not configured, all access is denied.
 */
class AdminBasicAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = (string) config('admin.username');
        $pass = (string) config('admin.password');

        $ok = $user !== '' && $pass !== ''
            && hash_equals($user, (string) $request->getUser())
            && hash_equals($pass, (string) $request->getPassword());

        if (! $ok) {
            return response('Reviewer credentials required.', 401, [
                'WWW-Authenticate' => 'Basic realm="Voxelion Review"',
            ]);
        }

        // Expose the reviewer identity downstream (audit + reviewer_id).
        $request->attributes->set('reviewer_id', $request->getUser());

        return $next($request);
    }
}

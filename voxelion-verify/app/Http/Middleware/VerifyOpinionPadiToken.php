<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Bearer-token guard for the /api/v1 server-to-server endpoints OpinionPadi
 * calls. Credentials live in config, never in the browser.
 */
class VerifyOpinionPadiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $expected = (string) config('opinionpadi.api_token');

        if (! $token || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

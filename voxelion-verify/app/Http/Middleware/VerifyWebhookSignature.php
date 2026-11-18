<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * HMAC-SHA256 guard for the /api/v1 server-to-server endpoints OpinionPadi
 * calls. The caller signs "<timestamp>.<raw body>" with the shared secret
 * and sends the result in X-OpinionPadi-Signature alongside
 * X-OpinionPadi-Timestamp. The timestamp window keeps captured signatures
 * from being replayed later. The secret lives in config, never in code.
 */
class VerifyWebhookSignature
{
    /** Maximum allowed clock skew between OpinionPadi and this app. */
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $timestamp = (string) $request->header('X-OpinionPadi-Timestamp', '');
        $signature = (string) $request->header('X-OpinionPadi-Signature', '');
        $secret = (string) config('opinionpadi.api_secret');

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

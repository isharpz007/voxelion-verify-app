<?php

namespace App\Http\Middleware;

use App\Services\HandoffException;
use App\Services\HandoffTokenService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The entry gate. Verifies the one-time RS256 hand-off JWT OpinionPadi
 * puts on the /start?t=… link — signature, issuer, expiry, and jti replay
 * (a jti already bound to a session is rejected by the service). On success
 * the verified claims are attached to the request for the controller; on
 * failure the Start page renders the matching link-error state.
 */
class ValidateHandoffToken
{
    public function __construct(private HandoffTokenService $tokens) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $claims = $this->tokens->verify((string) $request->query('t', ''));
        } catch (HandoffException $e) {
            return Inertia::render('Start', [
                'linkState' => $e->getMessage(), // invalid | expired | used | malformed
                'task' => null,
                'opinionpadiUrl' => config('opinionpadi.dashboard_url'),
            ]);
        }

        $request->attributes->set('handoffClaims', $claims);

        return $next($request);
    }
}

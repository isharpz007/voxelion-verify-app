<?php

namespace App\Http\Middleware;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Models\VerificationSession;
use App\Services\OpinionPadiWebhook;
use Closure;
use Illuminate\Http\Request;

/**
 * The journey gate. Every journey route declares the states it accepts via
 * middleware parameters; a session in any other state is redirected to the
 * correct step. Because state lives server-side, a reload or a network drop
 * resumes exactly where the volunteer left off.
 */
class EnforceSessionState
{
    public function handle(Request $request, Closure $next, string ...$allowed)
    {
        $session = $this->journeySession($request);

        if (! $session) {
            return redirect('/start');
        }

        if ($session->expires_at->isPast() && ! $session->state->isTerminal()) {
            $session->update(['state' => SessionState::Withdrawn]);

            // Notify OpinionPadi so the task does not hang awaiting a result.
            // Whether expiry should also trigger PII deletion is a privacy
            // decision (Phase 0 #4) — deliberately not taken here.
            app(OpinionPadiWebhook::class)->send('withdrawn', [
                'task_id' => $session->participant->task_id,
                'participant_ref' => $session->participant->op_volunteer_ref,
                'reason' => 'expired',
            ]);

            AuditRecorded::dispatch('system', 'session.expired', 'verification_session', $session->id);

            return redirect('/exit?reason=expired');
        }

        if (! in_array($session->state->value, $allowed, true)) {
            return redirect($session->state->route());
        }

        $request->attributes->set('verificationSession', $session);

        return $next($request);
    }

    private function journeySession(Request $request): ?VerificationSession
    {
        $id = $request->session()->get('verification_session_id');
        if (! $id) {
            return null;
        }

        return VerificationSession::with('participant', 'result', 'completionCode')->find($id);
    }
}

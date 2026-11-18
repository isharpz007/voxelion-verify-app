<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Participant;
use App\Models\VerificationSession;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StartController extends Controller
{
    /**
     * GET / — the welcome/entry screen. In local dev it shows the Stitch
     * welcome state and hands off through the dev JWT simulator. In
     * production the only way in is a signed OpinionPadi link via /start.
     */
    public function welcome(Request $request)
    {
        if (! app()->environment('local')) {
            return redirect('/start');
        }

        return Inertia::render('Start', [
            'linkState' => 'valid',
            'dev' => true,
            'task' => [
                'reference' => $request->query('task', 'OP-2026-7842'),
                'partner' => 'OpinionPadi',
                'duration' => '~2–3 mins',
            ],
        ]);
    }

    /**
     * GET/POST /start?t=… — the ValidateHandoffToken middleware has already
     * verified the JWT (and rendered the error state if not); the claims it
     * verified arrive as a request attribute.
     */
    public function __invoke(Request $request)
    {
        $claims = $request->attributes->get('handoffClaims');

        return $request->isMethod('post')
            ? $this->begin($request, $claims)
            : Inertia::render('Start', [
                'linkState' => 'valid',
                // Local only: the dev hand-off bounces through this page, so
                // the Start screen auto-begins instead of needing a 2nd click.
                'dev' => app()->environment('local'),
                'task' => [
                    'reference' => $claims['task_id'],
                    'partner' => 'OpinionPadi',
                    'duration' => '~2–3 mins',
                ],
            ]);
    }

    private function begin(Request $request, array $claims)
    {
        $participant = Participant::firstOrCreate(
            ['op_volunteer_ref' => $claims['sub'], 'task_id' => $claims['task_id']],
            ['submission_id' => $claims['submission_id']]
        );

        // Returning volunteer re-handed a new link: refresh the submission id.
        if ($claims['submission_id'] && $participant->submission_id !== $claims['submission_id']) {
            $participant->update(['submission_id' => $claims['submission_id']]);
        }

        $session = VerificationSession::create([
            'participant_id' => $participant->id,
            'state' => SessionState::Created,
            'handoff_jti' => $claims['jti'],
            'expires_at' => now()->addMinutes((int) config('verification.session_ttl_minutes')),
        ]);

        // Regenerate the session id at the authentication boundary so a
        // pre-handoff session id can never be carried into the journey.
        $request->session()->regenerate();
        $request->session()->put('verification_session_id', $session->id);

        AuditEvent::create([
            'actor' => 'participant:'.$participant->id,
            'action' => 'session.created',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
            'metadata_json' => ['task_id' => $claims['task_id']],
        ]);

        return redirect('/consent');
    }
}

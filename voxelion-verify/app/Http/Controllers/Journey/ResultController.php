<?php

namespace App\Http\Controllers\Journey;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResultController extends Controller
{
    public function __invoke(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;
        $result = $session->result;
        $code = $session->completionCode;

        return Inertia::render('Result', [
            'outcome' => $session->state->value, // verified | failed | manual_review
            'task' => ['reference' => $participant->task_id],
            'participant' => [
                'name' => $participant->full_name,
                'role' => $participant->volunteer_role_ref ?: 'Community Volunteer',
            ],
            'code' => $code ? $request->session()->get('completion_code_plain') : null,
            'codeExpiresAt' => $code?->expires_at->toIso8601String(),
            'similarityScore' => $result?->similarity_score,
            'verifiedAt' => $result?->completed_at?->toIso8601String(),
            'auditHash' => $result ? 'sha256:'.substr(hash('sha256', $result->id), 0, 5).'...'.substr(hash('sha256', $result->id), -4) : null,
            'opinionpadiUrl' => config('opinionpadi.dashboard_url'),
        ]);
    }
}

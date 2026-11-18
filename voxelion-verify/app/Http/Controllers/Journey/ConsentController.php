<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsentRequest;
use App\Models\AuditEvent;
use App\Models\Consent;
use App\Services\OpinionPadiWebhook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConsentController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Consent', [
            'task' => $this->taskContext($request),
            'consentVersion' => config('verification.consent_version'),
        ]);
    }

    public function store(ConsentRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $now = now();

        Consent::updateOrCreate(
            ['participant_id' => $session->participant_id],
            [
                'consent_version' => config('verification.consent_version'),
                'checkbox_1_at' => $now,
                'checkbox_2_at' => $now,
                'disclosure_hash' => hash('sha256', config('verification.consent_version')),
            ]
        );

        $session->update(['state' => SessionState::Consented]);
        $this->audit($session->id, 'consent.recorded');

        return redirect('/intake');
    }

    public function decline(Request $request)
    {
        $session = $request->attributes->get('verificationSession');

        Consent::updateOrCreate(
            ['participant_id' => $session->participant_id],
            [
                'consent_version' => config('verification.consent_version'),
                'declined_at' => now(),
                'disclosure_hash' => hash('sha256', config('verification.consent_version')),
            ]
        );

        $session->update(['state' => SessionState::Declined]);
        $this->audit($session->id, 'consent.declined');
        app(OpinionPadiWebhook::class)->send('withdrawn', [
            'task_id' => $session->participant->task_id,
            'reason' => 'declined',
        ]);

        return redirect('/exit?reason=declined');
    }

    private function taskContext(Request $request): array
    {
        $session = $request->attributes->get('verificationSession');

        return ['reference' => $session->participant->task_id, 'partner' => 'OpinionPadi'];
    }

    private function audit(string $sessionId, string $action): void
    {
        AuditEvent::create([
            'actor' => 'participant',
            'action' => $action,
            'entity_type' => 'verification_session',
            'entity_id' => $sessionId,
        ]);
    }
}

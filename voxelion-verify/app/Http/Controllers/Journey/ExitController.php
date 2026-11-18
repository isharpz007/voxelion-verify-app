<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDeletionRequest;
use App\Models\AuditEvent;
use App\Models\DeletionRequest;
use App\Services\OpinionPadiWebhook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExitController extends Controller
{
    public function __construct(private OpinionPadiWebhook $webhooks) {}

    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $reason = $request->query('reason', $session->state->value);

        $deletion = DeletionRequest::where('participant_id', $session->participant_id)->latest()->first();

        return Inertia::render('Exit', [
            'reason' => in_array($reason, ['declined', 'withdrawn', 'expired'], true) ? $reason : 'withdrawn',
            'deletionRef' => $deletion?->id ? 'DEL-'.strtoupper(substr($deletion->id, 0, 8)) : null,
            'task' => ['reference' => $session->participant->task_id],
            'opinionpadiUrl' => config('opinionpadi.dashboard_url'),
        ]);
    }

    public function withdraw(Request $request)
    {
        $session = $request->attributes->get('verificationSession');

        if (! $session->state->isTerminal()) {
            $session->update(['state' => SessionState::Withdrawn]);
        }

        $deletion = DeletionRequest::firstOrCreate(
            ['participant_id' => $session->participant_id],
            ['trigger' => 'withdrawal', 'status' => 'pending']
        );

        if ($deletion->status === 'pending') {
            ProcessDeletionRequest::dispatch($deletion->id);
        }

        AuditEvent::create([
            'actor' => 'participant',
            'action' => 'consent.withdrawn',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
        ]);

        $this->webhooks->send('withdrawn', [
            'task_id' => $session->participant->task_id,
            'participant_ref' => $session->participant->op_volunteer_ref,
        ]);

        return redirect('/exit?reason=withdrawn');
    }
}

<?php

namespace App\Http\Controllers\Journey;

use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExitController extends Controller
{
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
}

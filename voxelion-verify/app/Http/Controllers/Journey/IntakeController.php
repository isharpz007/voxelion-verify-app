<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Intake step: shows the saved contact form and OTP picker; update() saves participant details and drops verification markers when destinations change.
 */
class IntakeController extends Controller
{
    public function __construct(private OtpService $otps) {}

    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        // Details collected but neither contact channel verified yet —
        // the verify screen shows method selection (or an in-flight OTP).
        $contactSubmitted = $participant->email !== null && $participant->phone !== null;
        $verified = $participant->email_verified_at !== null || $participant->phone_verified_at !== null;

        // Email-only verification — the SMS channel remains wired server-side
        // (SmsSender/OtpService) but is not offered in the UI.
        $methods = collect(['email'])
            ->map(fn (string $channel) => ['channel' => $channel, 'destination' => $this->otps->maskedDestination($participant, $channel)])
            ->filter(fn (array $m) => $m['destination'] !== null)
            ->values();

        return Inertia::render('Intake', [
            'task' => ['reference' => $participant->task_id],
            'participant' => $participant->only('full_name', 'email', 'phone', 'phone_country_code'),
            'awaitingVerification' => $contactSubmitted && ! $verified,
            'contactMethods' => $methods,
            'otpStatus' => $this->otps->status($participant),
        ]);
    }

    public function update(IntakeRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;
        $validated = $request->validated();

        // A changed destination was never verified — drop that channel's
        // marker so the flag only ever describes verified contact details.
        if (($validated['email'] ?? null) !== $participant->email) {
            $participant->email_verified_at = null;
        }
        if (($validated['phone'] ?? null) !== $participant->phone
            || ($validated['phone_country_code'] ?? null) !== $participant->phone_country_code) {
            $participant->phone_verified_at = null;
        }

        $participant->update($validated);

        AuditRecorded::dispatch('participant', 'intake.submitted', 'verification_session', $session->id);

        // Returning via step navigation with a channel still verified means
        // nothing needs re-verifying — restore intake_done and move on.
        if (($participant->email_verified_at || $participant->phone_verified_at)
            && $session->state->canTransitionTo(SessionState::IntakeDone)) {
            $session->update(['state' => SessionState::IntakeDone]);

            return redirect('/photo');
        }

        // Stay in `consented` until an OTP is verified via /api/otp — only
        // then does the session advance to intake_done and /photo unlock.
        return redirect('/intake');
    }
}

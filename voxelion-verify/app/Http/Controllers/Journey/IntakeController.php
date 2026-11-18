<?php

namespace App\Http\Controllers\Journey;

use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $methods = collect(['email', 'sms'])
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

        $participant->update($request->validated());

        // Editing details after codes were issued invalidates nothing —
        // delivery uses the participant record at send time, so a resend
        // always targets the latest contact info.
        AuditRecorded::dispatch('participant', 'intake.submitted', 'verification_session', $session->id);

        // Stay in `consented` until an OTP is verified via /api/otp — only
        // then does the session advance to intake_done and /photo unlock.
        return redirect('/intake');
    }
}

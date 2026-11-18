<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Mail\EmailVerificationCode;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class IntakeController extends Controller
{
    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        return Inertia::render('Intake', [
            'task' => ['reference' => $participant->task_id],
            'participant' => $participant->only('full_name', 'email', 'phone', 'phone_country_code'),
            'awaitingVerification' => $participant->email_code_hash !== null && $participant->email_verified_at === null,
            // Local/dev only: surfaces the emailed code on-screen so the demo
            // journey works without reading laravel.log. Never set in prod.
            'devCode' => app()->environment('local') ? $request->session()->get('dev_email_code') : null,
        ]);
    }

    public function update(IntakeRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $participant->update($request->validated());
        $this->issueVerificationCode($request, $participant);

        AuditRecorded::dispatch('participant', 'intake.submitted', 'verification_session', $session->id);

        // Stay in `consented` until the emailed code is verified — only then
        // does the session advance to intake_done and /photo unlock.
        return redirect('/intake');
    }

    public function verify(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $data = $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $expected = (string) $participant->email_code_hash;
        $expired = ! $participant->email_code_expires_at || $participant->email_code_expires_at->isPast();

        if ($expected === '' || $expired || ! hash_equals($expected, $this->hashCode($data['code']))) {
            return back()->withErrors(['code' => 'That code is invalid or has expired request a new one.']);
        }

        $participant->update([
            'email_verified_at' => now(),
            'email_code_hash' => null,
            'email_code_expires_at' => null,
        ]);
        $session->update(['state' => SessionState::IntakeDone]);

        AuditRecorded::dispatch('participant', 'email.verified', 'verification_session', $session->id);

        return redirect('/photo');
    }

    public function resend(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        abort_unless($participant->email !== null, 422);

        $this->issueVerificationCode($request, $participant);

        return back();
    }

    private function issueVerificationCode(Request $request, Participant $participant): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $participant->update([
            'email_code_hash' => $this->hashCode($code),
            'email_code_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null,
        ]);

        Mail::to($participant->email)->send(new EmailVerificationCode($code));

        // Dev/testing convenience — the code is flashed into the session so
        // it can be shown on the intake screen and read by feature tests.
        if (app()->environment('local', 'testing')) {
            $request->session()->flash('dev_email_code', $code);
        }
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}

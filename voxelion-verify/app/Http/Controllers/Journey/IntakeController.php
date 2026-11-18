<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Mail\EmailVerificationCode;
use App\Models\Participant;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class IntakeController extends Controller
{
    public function __construct(private SmsSender $sms) {}

    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $awaiting = ($participant->email_code_hash !== null && $participant->email_verified_at === null)
            || ($participant->phone_code_hash !== null && $participant->phone_verified_at === null);

        return Inertia::render('Intake', [
            'task' => ['reference' => $participant->task_id],
            'participant' => $participant->only('full_name', 'email', 'phone', 'phone_country_code'),
            'awaitingVerification' => $awaiting,
            // Local/dev only: surfaces the emailed + SMSed codes on-screen so
            // the demo journey works without reading laravel.log. Never set in prod.
            'devCode' => app()->environment('local') ? $request->session()->get('dev_email_code') : null,
            'devSmsCode' => app()->environment('local') ? $request->session()->get('dev_sms_code') : null,
        ]);
    }

    public function update(IntakeRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $participant->update($request->validated());
        $this->issueVerificationCodes($request, $participant);

        AuditRecorded::dispatch('participant', 'intake.submitted', 'verification_session', $session->id);

        // Stay in `consented` until BOTH the emailed and SMSed codes are
        // verified — only then does the session advance to intake_done and
        // /photo unlock.
        return redirect('/intake');
    }

    public function verify(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $data = $request->validate([
            'email_code' => ['required', 'string', 'digits:6'],
            'sms_code' => ['required', 'string', 'digits:6'],
        ]);

        $errors = [];

        if (! $this->codeMatches($participant->email_code_hash, $participant->email_code_expires_at, $data['email_code'])) {
            $errors['email_code'] = 'That code is invalid or has expired — request a new one.';
        }
        if (! $this->codeMatches($participant->phone_code_hash, $participant->phone_code_expires_at, $data['sms_code'])) {
            $errors['sms_code'] = 'That code is invalid or has expired — request a new one.';
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        $participant->update([
            'email_verified_at' => now(),
            'email_code_hash' => null,
            'email_code_expires_at' => null,
            'phone_verified_at' => now(),
            'phone_code_hash' => null,
            'phone_code_expires_at' => null,
        ]);
        $session->update(['state' => SessionState::IntakeDone]);

        AuditRecorded::dispatch('participant', 'contact.verified', 'verification_session', $session->id, ['channels' => ['email', 'sms']]);

        return redirect('/photo');
    }

    public function resend(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        abort_unless($participant->email !== null && $participant->phone !== null, 422);

        $this->issueVerificationCodes($request, $participant);

        return back();
    }

    private function issueVerificationCodes(Request $request, Participant $participant): void
    {
        $emailCode = $this->newCode();
        $smsCode = $this->newCode();

        $participant->update([
            'email_code_hash' => $this->hashCode($emailCode),
            'email_code_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null,
            'phone_code_hash' => $this->hashCode($smsCode),
            'phone_code_expires_at' => now()->addMinutes(10),
            'phone_verified_at' => null,
        ]);

        Mail::to($participant->email)->send(new EmailVerificationCode($emailCode));
        $this->sms->send(
            $participant->phone_country_code.$participant->phone,
            "Your Voxelion verification code is {$smsCode}. It expires in 10 minutes."
        );

        // Dev/testing convenience — the codes are flashed into the session so
        // they can be shown on the intake screen and read by feature tests.
        if (app()->environment('local', 'testing')) {
            $request->session()->flash('dev_email_code', $emailCode);
            $request->session()->flash('dev_sms_code', $smsCode);
        }
    }

    private function codeMatches(?string $hash, mixed $expiresAt, string $code): bool
    {
        if (! $hash || ! $expiresAt || $expiresAt->isPast()) {
            return false;
        }

        return hash_equals($hash, $this->hashCode($code));
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}

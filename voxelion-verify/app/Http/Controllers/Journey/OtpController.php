<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Services\OtpDeliveryException;
use App\Services\OtpRateLimitException;
use App\Services\OtpService;
use App\Services\OtpVerificationException;
use Illuminate\Http\Request;

/**
 * Session-scoped OTP endpoints for the intake verification step. Mounted at
 * /api/otp/* inside the `web` middleware group — these are browser-session
 * endpoints for the volunteer, NOT the HMAC-authenticated OpinionPadi API.
 */
class OtpController extends Controller
{
    public function __construct(private OtpService $otps) {}

    public function send(Request $request)
    {
        $data = $request->validate(['channel' => ['required', 'in:email,sms']]);
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        try {
            $result = $this->otps->issue($participant, $data['channel']);
        } catch (OtpRateLimitException|OtpDeliveryException $e) {
            return back()->withErrors(['channel' => $e->getMessage()]);
        }

        AuditRecorded::dispatch('participant', 'otp.sent', 'verification_session', $session->id, ['channel' => $result['channel']]);

        return back();
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'digits:6']]);
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        try {
            $channel = $this->otps->verify($participant, $data['code']);
        } catch (OtpVerificationException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $participant->update([
            $channel === 'email' ? 'email_verified_at' : 'phone_verified_at' => now(),
        ]);
        $session->update(['state' => SessionState::IntakeDone]);

        AuditRecorded::dispatch('participant', 'otp.verified', 'verification_session', $session->id, ['channel' => $channel]);

        return redirect('/photo');
    }

    public function resend(Request $request)
    {
        // Resend = issue a fresh code over the requested (or last-used)
        // channel; OtpService enforces cooldown + window cap and invalidates
        // the previous OTP.
        return $this->send($request);
    }

    public function status(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $participant = $session->participant;

        $status = $this->otps->status($participant);

        return response()->json($status + [
            'verified' => $participant->email_verified_at !== null || $participant->phone_verified_at !== null,
        ]);
    }
}

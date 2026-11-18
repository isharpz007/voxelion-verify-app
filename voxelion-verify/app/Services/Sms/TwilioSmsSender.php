<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Twilio Messages API driver — covers Nigerian (+234) and international
 * numbers. Selected via services.sms.driver = 'twilio'.
 *
 * Chosen over Termii/Vonage for Phase-1 integration: strongest international
 * coverage, mature delivery receipts, and a plain REST API — no SDK needed.
 * Termii remains the shortlisted alternative if +234 delivery rates or
 * pricing prove better (see docs/EXTERNAL_CREDENTIALS_REQUIRED.md).
 *
 * Throws on any non-2xx so OtpService can invalidate the OTP rather than
 * claim a delivery that never happened.
 */
class TwilioSmsSender implements SmsSender
{
    public function __construct(
        private string $sid,
        private string $token,
        private string $from,
    ) {}

    public function send(string $to, string $message): void
    {
        $response = Http::asForm()
            ->withBasicAuth($this->sid, $this->token)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Twilio rejected the message: '.$response->json('message', $response->status()));
        }
    }
}

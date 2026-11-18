<?php

namespace App\Services\Sms;

/**
 * SMS delivery boundary. OTP codes for phone verification flow through this
 * interface so the SMS vendor (Twilio, Termii, Africa's Talking, …) can be
 * dropped in without touching the journey code — same pattern as
 * VerificationProvider for biometrics.
 */
interface SmsSender
{
    /**
     * Send an SMS to an E.164-ish number (country code + national digits).
     */
    public function send(string $to, string $message): void;
}

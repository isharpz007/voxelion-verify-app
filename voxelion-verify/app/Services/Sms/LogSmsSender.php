<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default development/testing driver — writes the SMS to the Laravel log
 * instead of calling a real gateway. Swap via services.sms.driver once the
 * SMS vendor contract lands (same procurement track as the face provider).
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info("SMS to {$to}: {$message}");
    }
}

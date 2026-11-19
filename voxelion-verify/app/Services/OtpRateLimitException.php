<?php

namespace App\Services;

/**
 * Thrown when OTP send/resend exceeds the cooldown or per-window send cap.
 */
class OtpRateLimitException extends \RuntimeException {}

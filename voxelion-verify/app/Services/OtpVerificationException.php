<?php

namespace App\Services;

/**
 * Thrown when an OTP verify attempt fails — wrong code, expired, consumed, or attempts exhausted.
 */
class OtpVerificationException extends \RuntimeException {}

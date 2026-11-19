<?php

namespace App\Services;

/**
 * Thrown when the mail/SMS provider rejects or fails to deliver an OTP.
 */
class OtpDeliveryException extends \RuntimeException {}

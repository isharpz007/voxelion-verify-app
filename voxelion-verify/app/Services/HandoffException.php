<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown when the OpinionPadi hand-off JWT fails signature, expiry, or claim validation.
 */
class HandoffException extends RuntimeException
{
    // message is one of: malformed | invalid | expired | used | unverifiable
}

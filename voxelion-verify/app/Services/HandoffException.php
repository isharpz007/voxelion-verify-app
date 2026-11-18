<?php

namespace App\Services;

use RuntimeException;

class HandoffException extends RuntimeException
{
    // message is one of: malformed | invalid | expired | used | unverifiable
}

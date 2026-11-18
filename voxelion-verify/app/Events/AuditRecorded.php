<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever something audit-worthy happens — consent steps, state
 * transitions, reviewer decisions, deletion jobs. Keeps the "what" at the
 * call site and the "how" (structured audit_events row) in one listener,
 * so NDPA traceability stays uniform everywhere.
 */
class AuditRecorded
{
    use Dispatchable;

    public function __construct(
        public string $actor,
        public string $action,
        public string $entityType,
        public string $entityId,
        public array $metadata = [],
    ) {}
}

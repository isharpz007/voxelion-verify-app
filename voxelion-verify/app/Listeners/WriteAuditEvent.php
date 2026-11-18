<?php

namespace App\Listeners;

use App\Events\AuditRecorded;
use App\Models\AuditEvent;

/**
 * The single writer to audit_events. Every audit trail entry flows
 * through here, so PII-minimisation rules (metadata must never carry
 * participant identifiers) have one enforcement point.
 */
class WriteAuditEvent
{
    /** Metadata keys that must never reach the audit log — PII stay out. */
    private const PII_KEYS = ['full_name', 'email', 'phone', 'phone_country_code', 'name'];

    public function handle(AuditRecorded $event): void
    {
        $metadata = $event->metadata === []
            ? null
            : array_diff_key($event->metadata, array_flip(self::PII_KEYS));

        AuditEvent::create([
            'actor' => $event->actor,
            'action' => $event->action,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'metadata_json' => $metadata === [] ? null : $metadata,
        ]);
    }
}

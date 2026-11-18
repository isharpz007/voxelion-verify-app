<?php

namespace App\Services;

use App\Models\CompletionCode;
use App\Models\VerificationSession;

/**
 * Issues and validates completion codes.
 * Format: VX-XXXX-XXXX-XXXX (Crockford base32 — no ambiguous chars),
 * stored hashed (sha256 + app key pepper via HMAC), single-use, expiring.
 */
class CompletionCodeService
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32 (no I,L,O,U)

    public function issueFor(VerificationSession $session): array
    {
        $plain = $this->generate();

        $record = CompletionCode::create([
            'code_hash' => $this->hash($plain),
            'session_id' => $session->id,
            'participant_id' => $session->participant_id,
            'issued_at' => now(),
            'expires_at' => now()->addHours((int) config('verification.code_ttl_hours')),
        ]);

        return ['plain' => $plain, 'record' => $record];
    }

    /**
     * Server-to-server validation used by OpinionPadi's reward review.
     * Never marks the code consumed — consumption happens on approval.
     */
    public function validate(string $code): array
    {
        $record = CompletionCode::where('code_hash', $this->hash($code))->first();

        if (! $record) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if ($record->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        if ($record->consumed_at !== null) {
            return ['valid' => false, 'reason' => 'consumed'];
        }

        $session = $record->session()->with('participant', 'result')->first();
        if (! $session || $session->state->value !== 'verified') {
            return ['valid' => false, 'reason' => 'not_verified'];
        }

        return [
            'valid' => true,
            'reason' => null,
            'code' => $record,
            'participant_ref' => $session->participant->op_volunteer_ref,
            'task_id' => $session->participant->task_id,
            'verified_at' => $session->result?->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Re-check a stored code record — used by the approval path where the
     * reviewer re-validates backend state rather than re-submitting plaintext.
     */
    public function validateRecord(CompletionCode $record): array
    {
        if ($record->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        if ($record->consumed_at !== null) {
            return ['valid' => false, 'reason' => 'consumed'];
        }

        $session = $record->session()->first();
        if (! $session || $session->state->value !== 'verified') {
            return ['valid' => false, 'reason' => 'not_verified'];
        }

        return ['valid' => true, 'reason' => null, 'code' => $record];
    }

    public function consume(CompletionCode $code, ?string $byTask = null): void
    {
        $code->update(['consumed_at' => now(), 'consumed_by_task' => $byTask]);
    }

    public function hash(string $plain): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plain));

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    private function generate(): string
    {
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $group = '';
            for ($i = 0; $i < 4; $i++) {
                $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $group;
        }

        return 'VX-'.implode('-', $groups);
    }
}

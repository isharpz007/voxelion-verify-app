<?php

namespace App\Console\Commands;

use App\Enums\SessionState;
use App\Models\AuditEvent;
use App\Models\CompletionCode;
use App\Models\MediaAsset;
use App\Models\RewardSubmission;
use App\Models\VerificationSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Retention housekeeping (Phase 6). Every retention window is config-driven
 * and disabled by default — the actual retention periods are a Legal/DPO
 * decision (Phase 0 decision #4), so this command only acts on windows that
 * have been explicitly set via environment variables.
 */
class PruneExpiredData extends Command
{
    protected $signature = 'voxelion:prune';

    protected $description = 'Apply configured retention windows to expired sessions, codes, media and audit rows.';

    public function handle(): int
    {
        $retention = config('verification.retention');

        $this->pruneSessions($retention['expired_session_days'] ?? null);
        $this->pruneCodes($retention['expired_code_days'] ?? null);
        $this->pruneAuditEvents($retention['audit_log_days'] ?? null);
        $this->pruneOrphanedMedia();

        return self::SUCCESS;
    }

    /**
     * Delete expired session records — but never a session that still backs a
     * live completion code or a pending reward submission, and never a
     * manual_review session (a reviewer decision may still be outstanding).
     * A session's expires_at is its 60-minute journey TTL, not the validity of
     * anything issued from it — that distinction is why the guards exist.
     */
    private function pruneSessions(?int $days): void
    {
        if ($days === null) {
            $this->line('retention.expired_session_days unset — skipping session prune.');

            return;
        }

        $protected = CompletionCode::where('expires_at', '>', now())
            ->pluck('session_id')
            ->merge(
                CompletionCode::whereIn(
                    'id',
                    RewardSubmission::where('review_state', 'pending')->whereNotNull('code_id')->pluck('code_id')
                )->pluck('session_id')
            )
            ->unique();

        $count = VerificationSession::where('expires_at', '<', now()->subDays($days))
            ->where('state', '!=', SessionState::ManualReview->value)
            ->whereNotIn('id', $protected)
            ->delete();
        $this->info("Pruned {$count} expired verification sessions.");
    }

    /** Delete completion-code records well past expiry (hash only — never plaintext). */
    private function pruneCodes(?int $days): void
    {
        if ($days === null) {
            $this->line('retention.expired_code_days unset — skipping code prune.');

            return;
        }

        $count = CompletionCode::where('expires_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$count} expired completion codes.");
    }

    private function pruneAuditEvents(?int $days): void
    {
        if ($days === null) {
            $this->line('retention.audit_log_days unset — skipping audit prune.');

            return;
        }

        $count = AuditEvent::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$count} audit events.");
    }

    /**
     * Always-on hygiene: remove media objects whose asset rows are gone
     * (e.g. a deletion job that removed the row but left the object).
     * Only runs against the local disk layout used in development.
     */
    private function pruneOrphanedMedia(): void
    {
        $known = MediaAsset::pluck('storage_key')->all();
        $orphans = 0;

        foreach (Storage::disk('media')->allFiles() as $path) {
            if (! in_array($path, $known, true)) {
                Storage::disk('media')->delete($path);
                $orphans++;
            }
        }

        if ($orphans) {
            $this->info("Removed {$orphans} orphaned media objects.");
        }
    }
}

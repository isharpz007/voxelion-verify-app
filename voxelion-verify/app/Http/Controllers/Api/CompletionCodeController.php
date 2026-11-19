<?php

namespace App\Http\Controllers\Api;

use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Services\CompletionCodeService;

/**
 * Signed OpinionPadi API endpoint: validates a completion code server-to-server. Read-only — consumption happens only on reward approval.
 */
class CompletionCodeController extends Controller
{
    public function __construct(private CompletionCodeService $codes) {}

    public function show(string $code)
    {
        $result = $this->codes->validate($code);

        AuditRecorded::dispatch('opinionpadi', 'code.validated', 'completion_code', $result['code']->id ?? $code, ['valid' => $result['valid'], 'reason' => $result['reason']]);

        if (! $result['valid']) {
            return response()->json(['valid' => false, 'reason' => $result['reason']], 200);
        }

        return response()->json([
            'valid' => true,
            'participant_ref' => $result['participant_ref'],
            'task_id' => $result['task_id'],
            'verified_at' => $result['verified_at'],
            'expires_at' => $result['code']->expires_at->toIso8601String(),
        ]);
    }
}

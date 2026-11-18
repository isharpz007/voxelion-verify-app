<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Services\CompletionCodeService;

class CompletionCodeController extends Controller
{
    public function __construct(private CompletionCodeService $codes) {}

    public function show(string $code)
    {
        $result = $this->codes->validate($code);

        AuditEvent::create([
            'actor' => 'opinionpadi',
            'action' => 'code.validated',
            'entity_type' => 'completion_code',
            'entity_id' => $result['code']->id ?? $code,
            'metadata_json' => ['valid' => $result['valid'], 'reason' => $result['reason']],
        ]);

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

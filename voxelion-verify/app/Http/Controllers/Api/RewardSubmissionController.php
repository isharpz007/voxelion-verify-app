<?php

namespace App\Http\Controllers\Api;

use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Models\RewardSubmission;
use App\Services\CompletionCodeService;
use Illuminate\Http\Request;

class RewardSubmissionController extends Controller
{
    public function __construct(private CompletionCodeService $codes) {}

    public function index(Request $request)
    {
        $query = RewardSubmission::with('participant', 'code')->latest();

        if ($state = $request->query('state')) {
            $query->where('review_state', $state);
        }

        // Project to the fields OpinionPadi actually needs — contact PII
        // (name/email/phone) must not leave this API (data minimisation).
        return response()->json($query->paginate(25)->through(fn ($s) => [
            'id' => $s->id,
            'task_id' => $s->task_id,
            'participant_ref' => $s->participant->op_volunteer_ref,
            'review_state' => $s->review_state,
            'reviewer_id' => $s->reviewer_id,
            'rejection_reason' => $s->rejection_reason,
            'has_screenshot' => (bool) $s->screenshot_s3_key,
            'code_expires_at' => $s->code?->expires_at?->toIso8601String(),
            'decision_at' => $s->decision_at?->toIso8601String(),
            'created_at' => $s->created_at?->toIso8601String(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'screenshot_s3_key' => ['nullable', 'string'],
        ]);

        $check = $this->codes->validate($data['code']);
        if (! $check['valid']) {
            return response()->json([
                'message' => 'Completion code rejected: '.$check['reason'],
                'valid' => false,
                'reason' => $check['reason'],
            ], 422);
        }

        $existing = RewardSubmission::where('code_id', $check['code']->id)->first();
        if ($existing) {
            return response()->json([
                'message' => 'A reward submission already exists for this code.',
                'submission' => $existing,
            ], 409);
        }

        $session = $check['code']->session()->with('participant')->first();

        $submission = RewardSubmission::create([
            'task_id' => $session->participant->task_id,
            'participant_id' => $session->participant_id,
            'code_id' => $check['code']->id,
            'screenshot_s3_key' => $data['screenshot_s3_key'] ?? null,
            'review_state' => 'pending',
        ]);

        AuditRecorded::dispatch('opinionpadi', 'reward.submitted', 'reward_submission', $submission->id);

        return response()->json(['submission' => $submission], 201);
    }

    public function decide(Request $request, string $id)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reviewer_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $submission = RewardSubmission::with('code.session')->findOrFail($id);

        if ($submission->review_state !== 'pending') {
            return response()->json(['message' => 'Submission already decided.'], 409);
        }

        if ($data['decision'] === 'approved') {
            // Backend code check — a screenshot alone is never sufficient.
            $check = $this->codes->validateRecord($submission->code);
            if (! $check['valid']) {
                return response()->json([
                    'message' => 'Cannot approve: completion code did not re-validate ('.$check['reason'].').',
                ], 422);
            }
            $this->codes->consume($submission->code, $submission->task_id);
        }

        $submission->update([
            'review_state' => $data['decision'],
            'reviewer_id' => $data['reviewer_id'],
            'rejection_reason' => $data['reason'] ?? null,
            'decision_at' => now(),
        ]);

        AuditRecorded::dispatch('reviewer:'.$data['reviewer_id'], 'reward.'.$data['decision'], 'reward_submission', $submission->id, ['reason' => $data['reason'] ?? null]);

        return response()->json(['submission' => $submission]);
    }
}

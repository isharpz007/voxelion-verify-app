<?php

namespace App\Http\Controllers\Api;

use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Participant;
use App\Models\RewardSubmission;
use App\Services\CompletionCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Signed OpinionPadi API endpoints: reward submissions + admin review. Approval requires a backend-validated code, never screenshot proof alone.
 */
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
            // Code entry is optional on OpinionPadi's proof screen — a
            // screenshot-only submission lands in the queue but can never
            // be approved (the exit criterion). Without a code, the caller
            // must identify the participant.
            'code' => ['required_without:participant_ref', 'nullable', 'string'],
            'participant_ref' => ['required_without:code', 'nullable', 'string'],
            'task_id' => ['nullable', 'string'],
            'screenshot' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'screenshot_s3_key' => ['nullable', 'string'],
        ]);

        $code = null;
        if (! empty($data['code'])) {
            $check = $this->codes->validate($data['code']);
            if (! $check['valid']) {
                return response()->json([
                    'message' => 'Completion code rejected: '.$check['reason'],
                    'valid' => false,
                    'reason' => $check['reason'],
                ], 422);
            }
            $code = $check['code'];

            $existing = RewardSubmission::where('code_id', $code->id)->first();
            if ($existing) {
                return response()->json([
                    'message' => 'A reward submission already exists for this code.',
                    'submission' => $existing,
                ], 409);
            }

            $participant = $code->session()->with('participant')->first()->participant;
        } else {
            $participant = Participant::where('op_volunteer_ref', $data['participant_ref'])
                ->when($data['task_id'] ?? null, fn ($q, $t) => $q->where('task_id', $t))
                ->first();

            if (! $participant) {
                return response()->json(['message' => 'Unknown participant reference.'], 404);
            }
        }

        $screenshotKey = $data['screenshot_s3_key'] ?? null;
        if ($request->hasFile('screenshot')) {
            // Store the proof on our media disk so reviewers get a signed
            // view URL and deletion workflows scrub it like all captures.
            $screenshotKey = 'media/'.$participant->id.'/proof-'.Str::uuid().'.'.$request->file('screenshot')->extension();
            Storage::disk('media')->put($screenshotKey, file_get_contents($request->file('screenshot')->getRealPath()));
            MediaAsset::create([
                'participant_id' => $participant->id,
                'kind' => 'proof',
                'storage_key' => $screenshotKey,
            ]);
        }

        $submission = RewardSubmission::create([
            'task_id' => $participant->task_id,
            'participant_id' => $participant->id,
            'code_id' => $code?->id,
            'screenshot_s3_key' => $screenshotKey,
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
            // Code-less (screenshot-only) submissions can never be approved.
            if (! $submission->code) {
                return response()->json([
                    'message' => 'Cannot approve: no completion code attached to this submission.',
                ], 422);
            }
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

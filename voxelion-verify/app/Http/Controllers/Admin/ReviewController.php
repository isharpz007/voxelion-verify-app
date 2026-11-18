<?php

namespace App\Http\Controllers\Admin;

use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\CompletionCode;
use App\Models\DeletionRequest;
use App\Models\RewardSubmission;
use App\Models\VerificationResult;
use App\Models\VerificationSession;
use App\Services\CompletionCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function __construct(private CompletionCodeService $codes) {}

    public function index(Request $request)
    {
        $ttl = now()->addMinutes((int) config('admin.media_url_ttl_minutes'));

        $submissions = RewardSubmission::with('participant.mediaAssets', 'code.session')
            ->latest()
            ->paginate(15);

        $submissions->getCollection()->transform(function (RewardSubmission $s) use ($ttl) {
            $check = $s->code ? $this->codes->validateRecord($s->code) : ['valid' => false, 'reason' => 'not_found'];

            return [
                'id' => $s->id,
                'task_id' => $s->task_id,
                'review_state' => $s->review_state,
                'rejection_reason' => $s->rejection_reason,
                'decision_at' => $s->decision_at?->toIso8601String(),
                'created_at' => $s->created_at->toIso8601String(),
                'participant' => [
                    'name' => $s->participant->full_name,
                    'ref' => $s->participant->op_volunteer_ref,
                ],
                'has_screenshot' => (bool) $s->screenshot_s3_key,
                'media' => $s->participant->mediaAssets->map(fn ($a) => [
                    'kind' => $a->kind,
                    'accepted' => (bool) $a->accepted,
                    'url' => URL::temporarySignedRoute('admin.media', $ttl, ['asset' => $a->id]),
                ])->all(),
                'code_validation' => $check,
                'code_expires_at' => $s->code?->expires_at->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/ReviewQueue', [
            'submissions' => $submissions,
            'pendingCount' => RewardSubmission::where('review_state', 'pending')->count(),
        ]);
    }

    public function decide(Request $request, string $id)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $submission = RewardSubmission::with('code')->findOrFail($id);

        if ($submission->review_state !== 'pending') {
            return redirect('/admin/review')->withErrors(['decision' => 'This submission has already been decided.']);
        }

        if ($data['decision'] === 'approved') {
            if (! $submission->code) {
                return redirect('/admin/review')->withErrors(['decision' => 'Cannot approve: no completion code attached to this submission.']);
            }
            $check = $this->codes->validateRecord($submission->code);
            if (! $check['valid']) {
                return redirect('/admin/review')->withErrors(['decision' => 'Cannot approve: code is '.$check['reason'].'.']);
            }
            $this->codes->consume($submission->code, $submission->task_id);
        }

        $reviewer = (string) ($request->attributes->get('reviewer_id') ?: $request->getUser() ?: 'admin-local');

        $submission->update([
            'review_state' => $data['decision'],
            'reviewer_id' => $reviewer,
            'rejection_reason' => $data['reason'] ?? null,
            'decision_at' => now(),
        ]);

        AuditRecorded::dispatch('reviewer:'.$reviewer, 'reward.'.$data['decision'], 'reward_submission', $submission->id, ['reason' => $data['reason'] ?? null]);

        return redirect('/admin/review');
    }

    /**
     * Lightweight operational metrics for the reviewer console — feeds the
     * Phase 6 dashboards/alerts (pass rates, failure rates, queue depth).
     */
    public function metrics()
    {
        return response()->json([
            'sessions_by_state' => VerificationSession::selectRaw('state, count(*) as total')
                ->groupBy('state')->pluck('total', 'state'),
            'verification_outcomes' => VerificationResult::selectRaw('outcome, count(*) as total')
                ->groupBy('outcome')->pluck('total', 'outcome'),
            'avg_similarity' => VerificationResult::avg('similarity_score'),
            'photo_attempts_total' => VerificationSession::sum('photo_attempts'),
            'liveness_attempts_total' => VerificationSession::sum('liveness_attempts'),
            'codes' => [
                'issued' => CompletionCode::count(),
                'consumed' => CompletionCode::whereNotNull('consumed_at')->count(),
                'expired_unconsumed' => CompletionCode::whereNull('consumed_at')
                    ->where('expires_at', '<', now())->count(),
            ],
            'reward_submissions' => RewardSubmission::selectRaw('review_state, count(*) as total')
                ->groupBy('review_state')->pluck('total', 'review_state'),
            'deletion_requests' => DeletionRequest::selectRaw('status, count(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
            'audit_events' => AuditEvent::count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}

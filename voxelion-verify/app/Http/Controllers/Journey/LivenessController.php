<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiveCompleteRequest;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\VerificationResult;
use App\Services\CompletionCodeService;
use App\Services\OpinionPadiWebhook;
use App\Services\VerificationProvider\VerificationProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LivenessController extends Controller
{
    public function __construct(
        private VerificationProvider $provider,
        private CompletionCodeService $codes,
        private OpinionPadiWebhook $webhooks,
    ) {}

    public function show(Request $request)
    {
        $session = $request->attributes->get('verificationSession');

        return Inertia::render('LiveCheck', [
            'task' => ['reference' => $session->participant->task_id],
            'challenge' => $session->current_challenge,
            'attemptsUsed' => $session->liveness_attempts,
            'maxAttempts' => (int) config('verification.liveness.max_retries'),
        ]);
    }

    public function challenge(Request $request)
    {
        $session = $request->attributes->get('verificationSession');
        $challenges = config('verification.liveness.challenges');
        $challenge = $challenges[array_rand($challenges)];

        $session->update([
            'current_challenge' => $challenge,
            'state' => SessionState::LiveCheck,
        ]);

        AuditEvent::create([
            'actor' => 'participant',
            'action' => 'liveness.challenge_issued',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
            'metadata_json' => ['challenge' => $challenge],
        ]);

        return response()->json(['challenge' => $challenge]);
    }

    public function complete(LiveCompleteRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $max = (int) config('verification.liveness.max_retries');

        if ($session->liveness_attempts >= $max) {
            return back()->withErrors(['capture' => 'Maximum attempts reached. Your verification is under manual review.']);
        }

        $challenge = $session->current_challenge ?? 'blink';
        $attempts = $session->liveness_attempts + 1;
        $session->update(['liveness_attempts' => $attempts]);

        $key = 'media/'.$session->participant_id.'/live-'.Str::uuid().'.'.$request->file('capture')->extension();
        Storage::disk('media')->put($key, file_get_contents($request->file('capture')->getRealPath()));

        MediaAsset::create([
            'participant_id' => $session->participant_id,
            'kind' => 'video',
            'storage_key' => $key,
        ]);

        $response = $this->provider->verifyLiveness(
            $challenge,
            Storage::disk('media')->path($key)
        );

        $threshold = (float) config('verification.liveness.similarity_threshold');
        $passed = $response['liveness_passed'] && $response['similarity_score'] >= $threshold;
        // A provider-declared hard failure (e.g. spoof detected) is terminal —
        // no retry is offered. The stub never emits one; real providers can.
        $hardFail = ($response['raw']['hard_fail'] ?? false) === true;

        $outcome = $passed ? 'verified' : ($hardFail ? 'failed' : ($attempts >= $max ? 'manual_review' : 'failed'));
        // A sub-limit failure stays in live_check so the volunteer can retry;
        // terminal failure is manual_review at the retry ceiling.
        $newState = $passed ? SessionState::Verified : ($hardFail ? SessionState::Failed : ($attempts >= $max ? SessionState::ManualReview : SessionState::LiveCheck));

        VerificationResult::create([
            'session_id' => $session->id,
            'challenge' => $challenge,
            'liveness_response_json' => $response['raw'],
            'similarity_score' => $response['similarity_score'],
            'outcome' => $outcome,
            'completed_at' => now(),
        ]);

        if ($passed) {
            $issued = $this->codes->issueFor($session);
            // Plain code is shown once on the result page — only the HMAC
            // hash is stored at rest.
            $request->session()->put('completion_code_plain', $issued['plain']);
        }

        $session->update(['state' => $newState, 'current_challenge' => null]);

        AuditEvent::create([
            'actor' => 'system',
            'action' => 'liveness.completed',
            'entity_type' => 'verification_session',
            'entity_id' => $session->id,
            'metadata_json' => ['outcome' => $outcome, 'score' => $response['similarity_score']],
        ]);

        // OpinionPadi is only notified of terminal outcomes — a retriable
        // attempt failure must not read as a final result on their side.
        $terminalEvent = $passed ? 'verified' : ($hardFail ? 'failed' : ($attempts >= $max ? 'manual_review' : null));
        if ($terminalEvent) {
            $this->webhooks->send($terminalEvent, [
                'task_id' => $session->participant->task_id,
                'participant_ref' => $session->participant->op_volunteer_ref,
            ]);
        }

        if (! $passed && ! $hardFail && $attempts < $max) {
            return back()->withErrors(['capture' => 'Verification failed — please try the challenge again.']);
        }

        return redirect('/result');
    }
}

<?php

namespace App\Http\Controllers\Journey;

use App\Enums\SessionState;
use App\Events\AuditRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiveCompleteRequest;
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

        AuditRecorded::dispatch('participant', 'liveness.challenge_issued', 'verification_session', $session->id, ['challenge' => $challenge]);

        return response()->json(['challenge' => $challenge]);
    }

    public function complete(LiveCompleteRequest $request)
    {
        $session = $request->attributes->get('verificationSession');
        $max = (int) config('verification.liveness.max_retries');

        if ($session->liveness_attempts >= $max) {
            return back()->withErrors(['capture' => 'Maximum attempts reached. Your verification is under manual review.']);
        }

        $key = 'media/'.$session->participant_id.'/live-'.Str::uuid().'.'.$request->file('capture')->extension();
        $disk = Storage::disk('media');
        $disk->put($key, file_get_contents($request->file('capture')->getRealPath()));

        // Accessibility fallback (Phase 0 decision): a still image submitted
        // when camera/liveness is not usable goes straight to manual review —
        // no liveness attempt is consumed and no automated verdict is made.
        if ($request->boolean('still')) {
            MediaAsset::create([
                'participant_id' => $session->participant_id,
                'kind' => 'still',
                'storage_key' => $key,
            ]);

            VerificationResult::create([
                'session_id' => $session->id,
                'challenge' => $session->current_challenge ?? 'accessibility',
                'liveness_response_json' => ['accessibility_fallback' => true],
                'similarity_score' => null,
                'outcome' => 'manual_review',
                'completed_at' => now(),
            ]);

            $session->update(['state' => SessionState::ManualReview, 'current_challenge' => null]);

            AuditRecorded::dispatch('participant', 'liveness.accessibility_fallback', 'verification_session', $session->id);

            $this->webhooks->send('manual_review', [
                'task_id' => $session->participant->task_id,
                'participant_ref' => $session->participant->op_volunteer_ref,
            ]);

            return redirect('/result');
        }

        $challenge = $session->current_challenge ?? 'blink';

        MediaAsset::create([
            'participant_id' => $session->participant_id,
            'kind' => 'video',
            'storage_key' => $key,
        ]);

        // path() is local-disk only — on S3 stream the capture to a temp
        // file so the provider gets a real filesystem path.
        $capturePath = $disk->path($key);
        if (! is_file($capturePath)) {
            $capturePath = tempnam(sys_get_temp_dir(), 'vox-live-');
            file_put_contents($capturePath, $disk->get($key));
        }

        try {
            $response = $this->provider->verifyLiveness($challenge, $capturePath);
        } catch (\Throwable $e) {
            // Provider latency spikes/timeouts must degrade gracefully:
            // the volunteer burned no attempt and can simply retry.
            report($e);
            AuditRecorded::dispatch('system', 'provider.error', 'verification_session', $session->id, ['stage' => 'liveness']);

            return back()->withErrors(['capture' => 'Verification service is temporarily unavailable — please try again in a moment.']);
        } finally {
            if (str_starts_with($capturePath, sys_get_temp_dir())) {
                @unlink($capturePath);
            }
        }

        // Only a provider response consumes an attempt — an outage is not
        // the volunteer's failure.
        $attempts = $session->liveness_attempts + 1;
        $session->update(['liveness_attempts' => $attempts]);

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

        AuditRecorded::dispatch('system', 'liveness.completed', 'verification_session', $session->id, ['outcome' => $outcome, 'score' => $response['similarity_score']]);

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

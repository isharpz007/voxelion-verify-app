<?php

namespace Tests\Feature;

use App\Jobs\DispatchWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_sends_signed_webhook(): void
    {
        Storage::fake('media');
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->post('/withdraw');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://opinionpadi.example/hook'
                && $body['event'] === 'withdrawn'
                && $request->hasHeader('X-Voxelion-Signature')
                && $request->header('X-Voxelion-Signature')[0] === hash_hmac('sha256', $request->body(), (string) config('opinionpadi.webhook_secret'));
        });
    }

    public function test_verified_outcome_sends_webhook(): void
    {
        Storage::fake('media');
        config(['opinionpadi.webhook_url' => 'https://opinionpadi.example/hook']);
        Http::fake();

        $session = $this->beginSession();
        $this->driveToPhoto($session);
        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        Http::assertSent(fn ($request) => str_contains($request->body(), '"event":"verified"'));
    }

    public function test_no_webhook_without_configured_url(): void
    {
        config(['opinionpadi.webhook_url' => null]);
        Http::fake();

        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->post('/withdraw');

        Http::assertNothingSent();
    }

    public function test_webhook_job_retries_with_backoff(): void
    {
        $job = new DispatchWebhook('verified', ['task_id' => 'T1'], 'https://example.com/h');

        $this->assertSame(5, $job->tries);
        $this->assertSame([10, 60, 300, 900], $job->backoff());
    }

    public function test_webhook_job_throws_on_http_failure(): void
    {
        Http::fake(['*' => Http::response('server error', 500)]);
        $job = new DispatchWebhook('verified', ['task_id' => 'T1'], 'https://example.com/h');

        $this->expectException(RequestException::class);
        $job->handle();
    }
}

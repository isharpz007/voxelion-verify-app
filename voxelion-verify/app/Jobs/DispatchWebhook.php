<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DispatchWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public string $event,
        public array $payload,
        public string $url,
    ) {}

    public function handle(): void
    {
        $body = json_encode(['event' => $this->event, 'data' => $this->payload]);
        $signature = hash_hmac('sha256', $body, (string) config('opinionpadi.webhook_secret'));

        Http::timeout(10)
            ->withHeaders(['X-Voxelion-Signature' => $signature])
            ->withBody($body, 'application/json')
            ->post($this->url)
            ->throw();
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }
}

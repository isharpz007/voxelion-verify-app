<?php

namespace App\Services;

use App\Jobs\DispatchWebhook;

/**
 * Queues HMAC-signed status events back to OpinionPadi
 * (verified | failed | manual_review | withdrawn).
 */
class OpinionPadiWebhook
{
    public function send(string $event, array $payload): void
    {
        $url = config('opinionpadi.webhook_url');
        if (! $url) {
            return;
        }

        DispatchWebhook::dispatch($event, $payload, $url);
    }
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpinionPadi integration settings
    |--------------------------------------------------------------------------
    | RS256 public key used to verify one-time hand-off JWTs minted by
    | OpinionPadi, the bearer token guarding the /api/v1 server-to-server
    | endpoints, and the shared secret used to sign outbound webhooks.
    */
    'issuer' => env('OPINIONPADI_ISSUER', 'opinionpadi'),
    'handoff_public_key' => env('OPINIONPADI_PUBLIC_KEY_PATH', storage_path('app/keys/opinionpadi_public.pem')),
    'handoff_max_age_seconds' => (int) env('OPINIONPADI_HANDOFF_TTL', 900), // 15 min
    'api_token' => env('OPINIONPADI_API_TOKEN', 'op-dev-token'),
    'webhook_secret' => env('OPINIONPADI_WEBHOOK_SECRET', 'dev-webhook-secret'),
    'webhook_url' => env('OPINIONPADI_WEBHOOK_URL', null),
    'dashboard_url' => env('OPINIONPADI_DASHBOARD_URL', 'https://opinionpadi.example/dashboard'),
];

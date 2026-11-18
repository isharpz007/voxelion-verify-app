<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Verification provider + journey settings
    |--------------------------------------------------------------------------
    | The VerificationProvider interface lets the vendor be swapped behind a
    | single binding (Phase 0 decision). The bundled VoxelionProvider simulates
    | liveness + similarity scoring until a real API contract is confirmed.
    */
    'provider' => env('VERIFICATION_PROVIDER', 'voxelion'),

    'session_ttl_minutes' => (int) env('VERIFICATION_SESSION_TTL', 60),
    'code_ttl_hours' => (int) env('COMPLETION_CODE_TTL', 72),

    'photo' => [
        'max_retries' => (int) env('PHOTO_MAX_RETRIES', 3),
        'max_size_kb' => 10240,
        'min_size_kb' => 10,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'min_width' => 480,
        'min_height' => 480,
    ],

    'liveness' => [
        'max_retries' => (int) env('LIVENESS_MAX_RETRIES', 3),
        'similarity_threshold' => (float) env('SIMILARITY_THRESHOLD', 0.80),
        'challenges' => ['blink', 'turn_left', 'turn_right'],
    ],

    'consent_version' => env('CONSENT_VERSION', 'v1.0-draft'),

    // Check the intake email's domain has MX/A/AAAA records — catches
    // typoed domains (gmdd.comm) before a code is mailed into the void.
    'email_dns_check' => env('EMAIL_DNS_CHECK', true),

    /*
    | Retention windows (Phase 6 housekeeping). All disabled by default —
    | actual periods are a Legal/DPO decision (Phase 0 decision #4). Set via
    | env once approved; `php artisan voxelion:prune` applies them.
    */
    'retention' => [
        'expired_session_days' => ($v = env('RETENTION_SESSION_DAYS')) === null ? null : (int) $v,
        'expired_code_days' => ($v = env('RETENTION_CODE_DAYS')) === null ? null : (int) $v,
        'audit_log_days' => ($v = env('RETENTION_AUDIT_DAYS')) === null ? null : (int) $v,
    ],
];

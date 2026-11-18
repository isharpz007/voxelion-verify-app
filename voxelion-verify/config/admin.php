<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reviewer console access
    |--------------------------------------------------------------------------
    | HTTP Basic credentials guarding /admin/*. Fails closed when unset —
    | in production these come from the secrets manager and the whole
    | reviewer surface should sit behind the staff SSO instead.
    */
    'username' => env('ADMIN_USERNAME', ''),
    'password' => env('ADMIN_PASSWORD', ''),

    // Minutes a signed media URL stays valid for reviewers.
    'media_url_ttl_minutes' => (int) env('ADMIN_MEDIA_URL_TTL', 15),
];

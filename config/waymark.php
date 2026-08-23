<?php

return [
    'staging' => env('WAYMARK_STAGING', env('APP_ENV') === 'staging'),
    'contact_submission_retention_days' => (int) env('WAYMARK_CONTACT_RETENTION_DAYS', 14),
    'public_cache_seconds' => (int) env('WAYMARK_PUBLIC_CACHE_SECONDS', 300),
    'anti_spam' => [
        'turnstile' => [
            'enabled' => env('ANTISPAM_PROVIDER', 'none') === 'turnstile',
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],
];

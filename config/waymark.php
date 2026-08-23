<?php

return [
    'contact_submission_retention_days' => (int) env('WAYMARK_CONTACT_RETENTION_DAYS', 14),
    'anti_spam' => [
        'turnstile' => [
            'enabled' => env('ANTISPAM_PROVIDER', 'none') === 'turnstile',
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],
];

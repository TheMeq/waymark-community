<?php

return [
    'version' => env('WAYMARK_VERSION', 'development'),
    'installation' => [
        'installed' => env('WAYMARK_INSTALLED'),
        'lock_path' => env('WAYMARK_INSTALLATION_LOCK', storage_path('app/private/installed.lock')),
        'legacy_application_key' => env('APP_KEY'),
    ],
    'scheduler' => [
        'heartbeat_path' => env('WAYMARK_SCHEDULER_HEARTBEAT_PATH', storage_path('app/private/scheduler-heartbeat.json')),
        'stale_after_minutes' => (int) env('WAYMARK_SCHEDULER_STALE_AFTER_MINUTES', 5),
        'fallback_state_path' => env('WAYMARK_FALLBACK_STATE_PATH', storage_path('app/private/fallback-last-run.json')),
        'fallback_cooldown_minutes' => (int) env('WAYMARK_FALLBACK_COOLDOWN_MINUTES', 5),
        'request_fallback_enabled' => (bool) env('WAYMARK_REQUEST_FALLBACK', env('APP_ENV') === 'production'),
    ],
    'updates' => [
        'metadata_url' => env('WAYMARK_RELEASE_METADATA_URL'),
    ],
    'backups' => [
        'destination_disk' => env('WAYMARK_BACKUP_DISK', 'backups'),
        'environment_path' => env('WAYMARK_ENVIRONMENT_PATH', base_path('.env')),
        'database_chunk_size' => (int) env('WAYMARK_BACKUP_DATABASE_CHUNK_SIZE', 100),
        'retention_count' => (int) env('WAYMARK_BACKUP_RETENTION_COUNT', 14),
    ],
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

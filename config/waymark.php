<?php

return [
    'version' => env('WAYMARK_VERSION', is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : 'development'),
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
        'public_key_base64' => env('WAYMARK_RELEASE_PUBLIC_KEY_BASE64'),
        'state_path' => env('WAYMARK_UPDATE_STATE_PATH', storage_path('app/private/update-state.json')),
        'application_root' => env('WAYMARK_APPLICATION_ROOT', base_path()),
        'staging_root' => env('WAYMARK_UPDATE_STAGING_ROOT', storage_path('framework/update-staging')),
    ],
    'backups' => [
        'destination_disk' => env('WAYMARK_BACKUP_DISK', 'backups'),
        'environment_path' => env('WAYMARK_ENVIRONMENT_PATH', base_path('.env')),
        'database_chunk_size' => (int) env('WAYMARK_BACKUP_DATABASE_CHUNK_SIZE', 100),
        'retention_count' => (int) env('WAYMARK_BACKUP_RETENTION_COUNT', 14),
        'restore_environment_path' => env('WAYMARK_RESTORE_ENVIRONMENT_PATH', base_path('.env')),
        'minimum_staging_bytes' => (int) env('WAYMARK_BACKUP_MINIMUM_STAGING_BYTES', 25 * 1024 * 1024),
    ],
    'recovery' => [
        'token_hash' => env('WAYMARK_RECOVERY_TOKEN_HASH'),
        'archive_directory' => env('WAYMARK_RECOVERY_ARCHIVE_DIRECTORY', storage_path('app/private/recovery-inbox')),
    ],
    'maintenance' => [
        'state_path' => env('WAYMARK_MAINTENANCE_STATE_PATH', storage_path('framework/waymark-maintenance.json')),
    ],
    'operations' => [
        'lock_path' => env('WAYMARK_OPERATION_LOCK_PATH', storage_path('framework/waymark-operation.lock')),
        'state_path' => env('WAYMARK_OPERATION_STATE_PATH', storage_path('framework/waymark-operation.json')),
        'journal_path' => env('WAYMARK_OPERATION_JOURNAL_PATH', storage_path('app/private/operation-audit.jsonl')),
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

<?php

return [
    'photo_policy' => [
        'current_version' => env('GALLERY_PHOTO_POLICY_VERSION', '1'),
    ],

    'photos' => [
        'disk' => env('GALLERY_PHOTOS_DISK', 'local'),
        'directory' => 'community-photos',
    ],

    'upload' => [
        'max_files' => (int) env('GALLERY_PHOTO_MAX_FILES', 10),
    ],

    'public' => [
        'downloads_enabled' => (bool) env('GALLERY_PUBLIC_DOWNLOADS_ENABLED', false),
        'per_page' => (int) env('GALLERY_PUBLIC_PER_PAGE', 18),
        'context_scan_limit' => (int) env('GALLERY_PUBLIC_CONTEXT_SCAN_LIMIT', 72),
    ],

    'deferred' => [
        'enabled' => (bool) env('GALLERY_DEFERRED_PROCESSING_ENABLED', true),
        'size_threshold_bytes' => (int) env('GALLERY_DEFERRED_PROCESSING_SIZE_THRESHOLD', 5 * 1024 * 1024),
        'max_attempts' => (int) env('GALLERY_DEFERRED_PROCESSING_MAX_ATTEMPTS', 3),
        'retry_delay_seconds' => (int) env('GALLERY_DEFERRED_PROCESSING_RETRY_DELAY', 60),
        'lease_minutes' => (int) env('GALLERY_DEFERRED_PROCESSING_LEASE_MINUTES', 15),
        'database_lock_wait_seconds' => (int) env('GALLERY_DEFERRED_PROCESSING_DATABASE_LOCK_WAIT_SECONDS', 5),
        'schedule_lock_minutes' => (int) env('GALLERY_DEFERRED_PROCESSING_SCHEDULE_LOCK_MINUTES', 5),
        'batch_threshold_files' => (int) env('GALLERY_DEFERRED_PROCESSING_BATCH_THRESHOLD', 3),
        'manual_fallback' => (bool) env('GALLERY_DEFERRED_PROCESSING_MANUAL_FALLBACK', true),
    ],

    'processing' => [
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
        'max_upload_bytes' => (int) env('GALLERY_PHOTO_MAX_UPLOAD_BYTES', 10 * 1024 * 1024),
        'max_width' => (int) env('GALLERY_PHOTO_MAX_WIDTH', 6000),
        'max_height' => (int) env('GALLERY_PHOTO_MAX_HEIGHT', 6000),
        'max_pixels' => (int) env('GALLERY_PHOTO_MAX_PIXELS', 24000000),
        'max_memory_bytes' => (int) env('GALLERY_PHOTO_MAX_MEMORY_BYTES', 128 * 1024 * 1024),
        'source_retention' => (bool) env('GALLERY_PHOTO_SOURCE_RETENTION', false),
        'retained_source' => ['max_width' => 4096, 'max_height' => 4096, 'quality' => 88],
        'variants' => [
            'master' => ['max_width' => 2560, 'max_height' => 2560, 'quality' => 86],
            'large' => ['max_width' => 1600, 'max_height' => 1600, 'quality' => 84],
            'medium' => ['max_width' => 960, 'max_height' => 960, 'quality' => 82],
            'thumbnail' => ['max_width' => 480, 'max_height' => 480, 'quality' => 80],
        ],
        'preferred_output_mime_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GALLERY_PHOTO_OUTPUT_MIME_TYPES', 'image/jpeg')),
        ))),
    ],
];

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

<?php

return [
    'photo_policy' => [
        'current_version' => env('GALLERY_PHOTO_POLICY_VERSION', '1'),
    ],

    'photos' => [
        'disk' => env('GALLERY_PHOTOS_DISK', 'local'),
        'directory' => 'community-photos',
    ],
];

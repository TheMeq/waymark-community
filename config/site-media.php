<?php

return [
    'profiles' => [
        'library' => [],
        'walk_featured_image' => [],
        'site_logo' => [
            'preferred_output_mime_types' => ['image/png'],
            'required_output_mime_type' => 'image/png',
        ],
        'site_favicon' => [
            'minimum_width' => 32,
            'minimum_height' => 32,
            'requires_square' => true,
            'variants' => [
                'favicon' => ['max_width' => 512, 'max_height' => 512, 'quality' => 90],
            ],
            'required_variants' => ['favicon'],
            'preferred_output_mime_types' => ['image/png'],
            'required_output_mime_type' => 'image/png',
        ],
    ],
];

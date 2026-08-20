<?php

return [
    'gpx' => [
        'disk' => env('WALK_GPX_DISK', 'local'),
        'directory' => 'walks/gpx',
        'max_bytes' => 5 * 1024 * 1024,
        'max_route_points' => 10000,
        'map_route_points' => 1000,
    ],

    'map' => [
        'tile_url' => env('WALK_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('WALK_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
        'max_route_points' => 1000,
    ],
];

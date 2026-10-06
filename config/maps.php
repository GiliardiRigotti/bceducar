<?php

use App\Geo\NominatimProvider;

return [
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    'max_zoom' => (int) env('MAP_MAX_ZOOM', 19),
    // Residential addresses must use an approved private/municipal instance.
    'geocoding_provider' => NominatimProvider::class,
    'geocoding_url' => env('GEOCODING_URL', ''),
    'user_agent' => env('GEOCODING_USER_AGENT', 'BC-Educar/1.0'),
    'cache_seconds' => 86400,
];

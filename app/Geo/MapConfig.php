<?php

namespace App\Geo;

class MapConfig
{
    public static function publicConfig(): array
    {
        return [
            'tileUrl' => config('maps.tile_url'),
            'attribution' => config('maps.attribution'),
            'maxZoom' => max(1, min(22, (int) config('maps.max_zoom', 19))),
            'defaultLatitude' => (float) config('prematricula.map.lat', -26.99),
            'defaultLongitude' => (float) config('prematricula.map.lng', -48.63),
            'defaultZoom' => (int) config('prematricula.map.zoom', 13),
        ];
    }
}

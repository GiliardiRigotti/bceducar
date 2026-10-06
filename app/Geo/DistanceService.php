<?php

namespace App\Geo;

use InvalidArgumentException;

class DistanceService
{
    // Approximate straight-line distance in metres; not routing or eligibility.
    public function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if (!GeocodingService::validCoordinates($lat1, $lng1) || !GeocodingService::validCoordinates($lat2, $lng2)) {
            throw new InvalidArgumentException('Coordenadas inválidas.');
        }
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
    }
}

<?php

namespace App\Geo;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GeocodingService
{
    public function geocode(string $address): ?array
    {
        return $this->query('search', ['q' => $address, 'limit' => 1]);
    }

    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        if (!self::validCoordinates($latitude, $longitude)) {
            throw new RuntimeException('Coordenadas inválidas.');
        }

        return $this->query('reverse', ['lat' => $latitude, 'lon' => $longitude]);
    }

    public static function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude)
            && is_finite((float) $latitude) && is_finite((float) $longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180;
    }

    private function query(string $operation, array $parameters): ?array
    {
        $providerClass = config('maps.geocoding_provider', NominatimProvider::class);
        $provider = app($providerClass);
        if (!$provider instanceof GeocodingProvider) {
            throw new RuntimeException('Invalid geocoding provider.');
        }
        $key = 'bc-geocode:'.hash('sha256', $providerClass.config('maps.geocoding_url').$operation.json_encode($parameters));
        $cached = Cache::remember($key, config('maps.cache_seconds'), function () use ($provider, $operation, $parameters) {
            if (!Cache::add('bc-geocode-provider-rate', true, 1)) {
                throw new RuntimeException('Try again in a moment.');
            }

            return ['result' => $provider->lookup($operation, $parameters)];
        });

        return $cached['result'];
    }
}

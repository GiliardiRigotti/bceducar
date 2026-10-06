<?php

namespace App\Geo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimProvider implements GeocodingProvider
{
    public function lookup(string $operation, array $parameters): ?array
    {
        $url = rtrim((string) config('maps.geocoding_url'), '/');
        $parts = parse_url($url);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!$url || !$host || !in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || $host === 'nominatim.openstreetmap.org' || str_ends_with($host, '.nominatim.openstreetmap.org')
            || (!app()->environment(['local', 'testing']) && $scheme !== 'https')) {
            throw new RuntimeException('Selecione a localização no mapa. A busca residencial requer um serviço privado ou municipal.');
        }
        $response = Http::timeout(8)->connectTimeout(3)->withoutRedirecting()->withUserAgent(config('maps.user_agent'))
            ->get($url.'/'.$operation, $parameters + ['format' => 'jsonv2'])->throw();
        if (!$response->successful()) {
            throw new RuntimeException('O serviço de localização não aceitou a consulta.');
        }
        $response = $response->json();
        $result = $operation === 'search' ? ($response[0] ?? null) : $response;
        if (!$result) {
            return null;
        }
        if (!GeocodingService::validCoordinates($result['lat'] ?? null, $result['lon'] ?? null)) {
            throw new RuntimeException('O serviço retornou coordenadas inválidas.');
        }

        return ['latitude' => (float) $result['lat'], 'longitude' => (float) $result['lon'],
            'formattedAddress' => (string) ($result['display_name'] ?? '')];
    }
}

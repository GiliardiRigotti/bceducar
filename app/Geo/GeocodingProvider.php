<?php

namespace App\Geo;

interface GeocodingProvider
{
    public function lookup(string $operation, array $parameters): ?array;
}

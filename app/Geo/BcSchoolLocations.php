<?php

namespace App\Geo;

use App\Models\City;
use App\Models\LegacySchool;
use App\Models\Place;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BcSchoolLocations
{
    public function catalog(): array
    {
        $catalog = json_decode(file_get_contents(resource_path('data/bc-school-locations.json')), true, flags: JSON_THROW_ON_ERROR);
        $points = [];
        foreach ($catalog['schools'] as $location) {
            if (!GeocodingService::validCoordinates($location['latitude'], $location['longitude'])
                || $location['latitude'] < -27.10 || $location['latitude'] > -26.94
                || $location['longitude'] < -48.66 || $location['longitude'] > -48.55
                || !$location['address'] || !$location['address_source'] || !$location['coordinate_source']) {
                throw new RuntimeException('Invalid BC school location catalog.');
            }
            $point = $location['latitude'].','.$location['longitude'];
            if (isset($points[$point])) {
                throw new RuntimeException('Duplicate school coordinates in BC catalog.');
            }
            $points[$point] = true;
        }

        return $catalog['schools'];
    }

    public function apply(LegacySchool $school, array $location): void
    {
        DB::transaction(function () use ($school, $location) {
            $school = LegacySchool::query()->lockForUpdate()->findOrFail($school->getKey());
            $addresses = $school->addresses()->wherePivot('type', 1)->get();
            if ($addresses->count() > 1) {
                throw new RuntimeException('School has multiple main addresses; resolve before synchronization.');
            }
            $place = $addresses->first();
            if ($place && DB::table('person_has_place')->where('place_id', $place->id)
                ->where('person_id', '!=', $school->ref_idpes)->exists()) {
                throw new RuntimeException('Shared school address cannot be overwritten.');
            }
            $attributes = array_intersect_key($location, array_flip([
                'address', 'number', 'neighborhood', 'postal_code', 'complement', 'latitude', 'longitude',
            ]));
            $attributes['city_id'] = City::query()->where('ibge_code', $location['city_ibge_code'])->firstOrFail()->getKey();
            if ($place) {
                $place->update($attributes);
            } else {
                $place = Place::query()->create($attributes);
                $school->addresses()->attach($place->id, ['type' => 1]);
            }
            $school->update(['latitude' => $location['latitude'], 'longitude' => $location['longitude']]);
        });
    }
}

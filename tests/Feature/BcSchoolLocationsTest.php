<?php

namespace Tests\Feature;

use App\Geo\BcSchoolLocations;
use App\Models\BcDemoEntity;
use App\Models\LegacySchool;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BcSchoolLocationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
    }

    public function test_all_existing_schools_have_distinct_points_and_native_addresses_shared_by_pmd(): void
    {
        $locations = app(BcSchoolLocations::class);
        $catalog = $locations->catalog();
        $this->assertSame(array_keys(BalnearioCamboriuDemoSeeder::SCHOOLS), array_keys($catalog));
        foreach ($catalog as $slug => $location) {
            $id = BcDemoEntity::query()->where('key', 'school.'.$slug)->value('legacy_id');
            $school = LegacySchool::query()->findOrFail($id);
            $locations->apply($school, $location);
            $placeId = $school->addresses()->wherePivot('type', 1)->firstOrFail()->id;
            $locations->apply($school, $location);
            $this->assertSame(1, $school->addresses()->wherePivot('type', 1)->count());
            $this->assertSame($placeId, $school->addresses()->wherePivot('type', 1)->firstOrFail()->id);
            $address = DB::table('relatorio.view_dados_escola')->where('cod_escola', $id)->first();
            $this->assertSame($location['address'], $address->logradouro);
            $this->assertSame($location['neighborhood'], $address->bairro);
            $this->assertSame('Balneário Camboriú', $address->municipio);
            $pmd = DB::table('schools')->find($id);
            $this->assertEqualsWithDelta($location['latitude'], (float) $pmd->latitude, 0.000001);
            $this->assertEqualsWithDelta($location['longitude'], (float) $pmd->longitude, 0.000001);
        }
        $this->assertSame(15, count(array_unique(array_map(fn ($row) => $row['latitude'].','.$row['longitude'], $catalog))));
    }

    public function test_preview_does_not_change_school_coordinates(): void
    {
        $school = LegacySchool::query()->findOrFail(BcDemoEntity::query()->where('key', 'school.medici')->value('legacy_id'));
        $school->update(['latitude' => -26.99, 'longitude' => -48.63]);
        $this->artisan('bc:sync-school-locations')->assertSuccessful();
        $this->assertEqualsWithDelta(-26.99, (float) $school->fresh()->latitude, 0.000001);
    }
}

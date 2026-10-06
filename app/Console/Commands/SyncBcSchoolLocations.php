<?php

namespace App\Console\Commands;

use App\Geo\BcSchoolLocations;
use App\Models\BcDemoEntity;
use App\Models\LegacySchool;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class SyncBcSchoolLocations extends Command
{
    protected $signature = 'bc:sync-school-locations {--apply : Apply the reviewed catalog after saving a backup}';

    protected $description = 'Synchronize reviewed addresses and map coordinates for existing BC school registrations';

    public function handle(BcSchoolLocations $locations): int
    {
        $plan = [];
        foreach ($locations->catalog() as $slug => $location) {
            $id = BcDemoEntity::query()->where('source', BalnearioCamboriuDemoSeeder::SOURCE)
                ->where('key', 'school.'.$slug)->value('legacy_id');
            $school = $id ? LegacySchool::query()->find($id) : null;
            if (!$school || Str::lower(Str::ascii($school->name)) !== Str::lower(Str::ascii($location['name']))) {
                $this->error('School registration does not match catalog: '.$slug);

                return self::FAILURE;
            }
            $plan[] = [$school, $location];
        }
        $this->table(['ID', 'School', 'Address', 'Neighborhood', 'Latitude', 'Longitude'], array_map(
            fn ($item) => [$item[0]->getKey(), $item[0]->name, $item[1]['address'].' '.($item[1]['number'] ?? 's/n'),
                $item[1]['neighborhood'], $item[1]['latitude'], $item[1]['longitude']], $plan));
        if (!$this->option('apply')) {
            $this->info('Read-only preview. Use --apply to synchronize these registrations.');

            return self::SUCCESS;
        }
        $backup = storage_path('app/school-location-backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.json');
        File::ensureDirectoryExists(dirname($backup));
        File::put($backup, json_encode(array_map(fn ($item) => [
            'school_id' => $item[0]->getKey(), 'person_id' => $item[0]->ref_idpes,
            'latitude' => $item[0]->latitude, 'longitude' => $item[0]->longitude,
            'addresses' => $item[0]->addresses()->wherePivot('type', 1)->get()->toArray(),
        ], $plan), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        try {
            DB::transaction(function () use ($locations, $plan) {
                foreach ($plan as [$school, $location]) {
                    $locations->apply($school, $location);
                    $pmd = DB::table('schools')->find($school->getKey());
                    if (!$pmd || abs((float) $pmd->latitude - $location['latitude']) > 0.000001
                        || abs((float) $pmd->longitude - $location['longitude']) > 0.000001) {
                        throw new RuntimeException('PMD school coordinates differ from the native school registration.');
                    }
                }
            });
        } catch (\Throwable $error) {
            $this->error('Synchronization rolled back: '.$error->getMessage());

            return self::FAILURE;
        }
        $this->info(count($plan).' schools synchronized. Public school-location backup: '.$backup);
        $this->warn('NEI Pioneiros: updated address; public reference point requires confirmation of the new entrance.');

        return self::SUCCESS;
    }
}

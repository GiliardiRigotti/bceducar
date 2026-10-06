<?php

use App\Geo\BcSchoolLocations;
use App\Models\BcDemoEntity;
use App\Models\LegacySchool;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$points = [];
foreach (app(BcSchoolLocations::class)->catalog() as $slug => $location) {
    $id = BcDemoEntity::query()->where('key', 'school.'.$slug)->value('legacy_id');
    $school = LegacySchool::query()->findOrFail($id);
    $addresses = $school->addresses()->wherePivot('type', 1)->get();
    $pmd = DB::table('schools')->find($id);
    $native = DB::table('relatorio.view_dados_escola')->where('cod_escola', $id)->first();
    if ($addresses->count() !== 1 || $native?->logradouro !== $location['address']
        || $native->bairro !== $location['neighborhood']
        || !$pmd || abs((float) $pmd->latitude - $location['latitude']) > 0.000001
        || abs((float) $pmd->longitude - $location['longitude']) > 0.000001
        || abs((float) $addresses[0]->latitude - $location['latitude']) > 0.000001
        || abs((float) $addresses[0]->longitude - $location['longitude']) > 0.000001) {
        fwrite(STDERR, 'School location mismatch: '.$slug.PHP_EOL);
        exit(1);
    }
    $points[] = $pmd->latitude.','.$pmd->longitude;
    echo $location['name'].' | '.$location['neighborhood'].' | '.$pmd->latitude.', '.$pmd->longitude.PHP_EOL;
}
if (count(array_unique($points)) !== 15) {
    exit(1);
}
echo 'OK: 15 main addresses, 15 distinct markers, native coordinates and PMD consistent.'.PHP_EOL;

<?php

namespace Tests\Feature;

use App\Models\BcDemoEntity;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoricalExpiryReviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_is_read_only_excludes_open_and_unrelated_rejections_and_deduplicates_evidence(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $source = PreRegistration::query()->firstOrFail();
        $school = $source->school_id;
        $process = $source->process_id;
        $baseline = $this->report($process, $school)['total'];
        $created = [];
        foreach ([
            [PreRegistration::STATUS_REJECTED, 'DEADLINE_EXPIRED'],
            [PreRegistration::STATUS_REJECTED, 'AWAITING_DOCUMENTS'],
            [PreRegistration::STATUS_SUMMONED, 'DEADLINE_EXPIRED'],
            [PreRegistration::STATUS_REJECTED, 'AWAITING_DOCUMENTS'],
        ] as [$status, $documentStatus]) {
            $pmd = $source->replicate(['position']);
            $pmd->protocol = 'REVIEW-'.Str::random(12);
            $pmd->code = md5($pmd->protocol);
            $pmd->status = $status;
            $pmd->documentation_status = $documentStatus;
            $pmd->save();
            $created[] = $pmd;
        }
        // A rejected record with both repeated expiry events and a BC link counts once.
        foreach ([1, 2] as $_) {
            DB::table('preregistration_document_events')->insert([
                'preregistration_id' => $created[1]->id, 'event' => 'DOCUMENT_DEADLINE_EXPIRED',
                'actor_type' => 'SYSTEM', 'metadata' => '{}', 'created_at' => now(),
            ]);
        }
        $linked = RegistrationRequest::firstOrFail()->replicate();
        $linked->protocol = 'REVIEW-BC-'.Str::random(12);
        $linked->pmd_preregistration_id = $created[1]->id;
        $linked->status = 'PRAZO_EXPIRADO';
        $linked->save();
        $before = DB::table('preregistrations')->orderBy('id')->get()->toJson();
        $bcBefore = DB::table('bc_registration_requests')->orderBy('id')->get()->toJson();
        $result = $this->report($process, $school);
        $this->assertSame($baseline + 2, $result['total']);
        $this->assertTrue($result['read_only']);
        $this->assertSame('INDICATION_REQUIRES_MANUAL_REVIEW', $result['classification']);
        $this->assertStringNotContainsString($created[0]->protocol, json_encode($result));
        $this->assertSame($before, DB::table('preregistrations')->orderBy('id')->get()->toJson());
        $this->assertSame($bcBefore, DB::table('bc_registration_requests')->orderBy('id')->get()->toJson());
        $this->assertSame(0, $this->report($process, 2147483647)['total']);
    }

    private function report(int $process, int $school): array
    {
        $this->assertSame(0, Artisan::call('bc:historical-expiry-review', [
            '--process' => $process, '--school' => $school, '--json' => true,
        ]));

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_filters_reject_non_positive_ids(): void
    {
        foreach (['0', '-1', 'invalid'] as $value) {
            $this->artisan('bc:historical-expiry-review', ['--process' => $value])->assertFailed();
        }
    }
}

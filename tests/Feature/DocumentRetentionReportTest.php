<?php

namespace Tests\Feature;

use App\Models\BcDemoEntity;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentRetentionReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_preserves_files_and_excludes_open_applications(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Storage::fake('registration-documents');
        if (!BcDemoEntity::where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $source = RegistrationRequest::firstOrFail();
        $sourceDocument = $source->documents()->firstOrFail();
        foreach (['CANCELADA', 'AGUARDANDO_DOCUMENTOS'] as $status) {
            $request = $source->replicate();
            $request->protocol = 'RETENTION-'.Str::random(10);
            $request->pmd_preregistration_id = null;
            $request->status = $status;
            $request->timestamps = false;
            $request->created_at = now()->subDays(10);
            $request->updated_at = now()->subDays(10);
            $request->save();
            $document = $sourceDocument->replicate();
            $document->registration_request_id = $request->id;
            $document->path = 'retention/'.$status.'.pdf';
            $document->timestamps = false;
            $document->created_at = now()->subDays(10);
            $document->updated_at = now()->subDays(10);
            $document->save();
            Storage::disk('registration-documents')->put($document->path, 'fixture');
        }
        $before = DB::table('bc_registration_documents')->count();
        $this->artisan('bc:document-retention-report', ['--days' => '5'])
            ->expectsOutputToContain('Simulação')
            ->expectsOutputToContain('Total para avaliação: 1.')
            ->expectsOutputToContain('Nenhum arquivo ou registro foi removido.')->assertSuccessful();
        $this->assertSame($before, DB::table('bc_registration_documents')->count());
        Storage::disk('registration-documents')->assertExists(['retention/CANCELADA.pdf', 'retention/AGUARDANDO_DOCUMENTOS.pdf']);
    }

    public function test_missing_policy_and_invalid_days_are_rejected(): void
    {
        config(['bc-retention.days' => null, 'bc-retention.approved' => false]);
        $this->artisan('bc:document-retention-report')->assertFailed();
        $this->artisan('bc:document-retention-report', ['--days' => '-1'])->assertFailed();
    }
}

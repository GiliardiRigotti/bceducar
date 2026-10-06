<?php

namespace Tests\Feature;

use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PmdDocumentConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        // Historical demo links represent already deferred preregistrations.
        DB::table('preregistrations')->where('status', PreRegistration::STATUS_WAITING)
            ->whereIn('id', RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
                ->whereNotNull('pmd_preregistration_id')->select('pmd_preregistration_id'))
            ->update(['status' => PreRegistration::STATUS_SUMMONED]);

    }

    private function account(string $login): LegacyUser
    {
        $id = BcDemoEntity::query()->where('source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->where('key', 'user.' . $login)->value('legacy_id');

        return LegacyUser::query()->findOrFail($id);
    }

    public function test_institutional_administrator_can_configure_own_process_without_changing_old_applications(): void
    {
        $process = DB::table('preregistrations')->orderBy('id')->firstOrFail();
        $before = DB::table('preregistrations')->where('id', $process->id)->firstOrFail();
        $this->actingAs($this->account('admin.seduc'));
        $this->get('/bc/matriculas/configuracao?process=' . $process->process_id)->assertOk();
        $this->post('/bc/matriculas/configuracao/tipos', [
            'name' => 'Certidão teste', 'description' => 'Documento de demonstração',
            'code' => 'CERTIDAO_NASCIMENTO',
        ])->assertRedirect();
        $type = DB::table('preregistration_document_types')->where('name', 'Certidão teste')->firstOrFail();

        $this->post('/bc/matriculas/configuracao/processos/' . $process->process_id, [
            'documentation_deadline' => '2026-12-15 17:00',
            'documents' => [$type->id => ['enabled' => '1', 'required' => '1']],
        ])->assertRedirect();
        $this->assertDatabaseHas('process_document_types', [
            'process_id' => $process->process_id, 'document_type_id' => $type->id, 'required' => true,
        ]);
        $after = DB::table('preregistrations')->where('id', $process->id)->firstOrFail();
        $this->assertSame($before->status, $after->status);
        $this->assertNull($after->documentation_deadline);
    }

    public function test_operator_can_set_delivery_and_retry_days_with_positive_limits(): void
    {
        $processId = DB::table('preregistrations')->orderBy('id')->value('process_id');
        $this->actingAs($this->account('admin.seduc'));
        $this->post('/bc/matriculas/configuracao/processos/'.$processId, [
            'documentation_delivery_days' => 10, 'documentation_retry_days' => 4,
            'physical_delivery_days' => 12, 'physical_retry_days' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('processes', ['id' => $processId,
            'documentation_delivery_days' => 10, 'documentation_retry_days' => 4,
            'physical_delivery_days' => 12, 'physical_retry_days' => 5, 'documentation_deadline' => null]);
        foreach ([0, 366] as $invalid) {
            $this->postJson('/bc/matriculas/configuracao/processos/'.$processId, [
                'documentation_delivery_days' => $invalid, 'documentation_retry_days' => $invalid,
                'physical_delivery_days' => $invalid, 'physical_retry_days' => $invalid,
            ])->assertUnprocessable();
        }
    }

    public function test_school_operator_cannot_change_document_policy(): void
    {
        $processId = DB::table('preregistrations')->orderBy('id')->value('process_id');
        $this->actingAs($this->account('operador.medici'));
        $this->get('/bc/matriculas/configuracao')->assertForbidden();
        $this->post('/bc/matriculas/configuracao/tipos', ['name' => 'Não permitido'])->assertForbidden();
        $this->post('/bc/matriculas/configuracao/processos/' . $processId, [
            'documentation_deadline' => '2026-12-15 17:00',
        ])->assertForbidden();
    }

    public function test_process_policy_is_snapshotted_into_new_document_request_and_blocks_early_approval(): void
    {
        $actor = $this->account('admin.seduc');
        $existing = RegistrationRequest::query()->where('protocol', 'BC-2026-0002')->firstOrFail();
        $pmdId = $existing->pmd_preregistration_id;
        $processId = DB::table('preregistrations')->where('id', $pmdId)->value('process_id');
        $type = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Nascimento', 'code' => 'CERTIDAO_NASCIMENTO', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert([
            'process_id' => $processId, 'document_type_id' => $type, 'required' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $existing->update(['pmd_preregistration_id' => null]);
        $imported = app(PmdIntake::class)->import($pmdId, $existing->school_class_id, $actor);
        $this->assertSame(1, $imported->documents()->count());
        $this->assertTrue($imported->documents()->firstOrFail()->required);
        $this->expectException(ValidationException::class);
        app(RegistrationWorkflow::class)->approve($imported, $actor);
    }
}

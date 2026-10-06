<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExtendedDocumentCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $original;

    private LegacyUser $actor;

    private int $pmdId;

    private int $processId;

    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Storage::fake('registration-documents');
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $this->actor = LegacyUser::findOrFail(BcDemoEntity::where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($this->actor);
        $this->original = RegistrationRequest::where('protocol', 'BC-2026-0008')->firstOrFail();
        $this->pmdId = $this->original->pmd_preregistration_id;
        $this->processId = DB::table('preregistrations')->where('id', $this->pmdId)->value('process_id');
        $this->original->update(['pmd_preregistration_id' => null]);
        DB::table('preregistrations')->where('id', $this->pmdId)->update(['status' => PreRegistration::STATUS_WAITING,
            'documentation_status' => null, 'document_submission_method' => null, 'documentation_deadline' => null]);
        DB::table('processes')->where('id', $this->processId)->update(['documentation_deadline' => now()->addDays(7), 'document_workflow_enabled' => true]);
        DB::table('process_document_types')->where('process_id', $this->processId)->delete();
        $this->post('/bc/matriculas/configuracao/tipos', ['name' => 'Declaração adicional', 'code' => 'DECLARACAO_ADICIONAL'])->assertRedirect()->assertSessionHasNoErrors();
        $this->typeId = DB::table('preregistration_document_types')->where('code', 'DECLARACAO_ADICIONAL')->value('id');
        DB::table('process_document_types')->insert(['process_id' => $this->processId, 'document_type_id' => $this->typeId,
            'required' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function release(): RegistrationRequest
    {
        return app(PmdIntake::class)->import($this->pmdId, $this->original->school_class_id, $this->actor);
    }

    public function test_custom_category_upload_correction_and_approval_preserve_snapshot(): void
    {
        $request = $this->release();
        $this->assertSame(1, $request->documents()->count());
        $this->assertSame('DECLARACAO_ADICIONAL', $request->documents()->first()->document_type->value);
        $this->assertSame('DECLARACAO_ADICIONAL', $request->documents()->first()->toArray()['document_type']);
        DB::table('preregistration_document_types')->where('id', $this->typeId)->update(['name' => 'Nome alterado', 'active' => false]);
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/matricula-digital/documentos', ['document_type' => 'DECLARACAO_ADICIONAL', 'document' => UploadedFile::fake()->image('arquivo.png')])->assertRedirect()->assertSessionHasNoErrors();
        $document = $request->documents()->first();
        $workflow = app(RegistrationWorkflow::class);
        $workflow->review($document, $this->actor, DocumentStatus::Correction, 'Enviar legível');
        $this->post('/matricula-digital/documentos', ['document_type' => 'DECLARACAO_ADICIONAL', 'replaces_id' => $document->id, 'document' => UploadedFile::fake()->image('corrigido.png')])->assertRedirect()->assertSessionHasNoErrors();
        $current = $request->documents()->latest('id')->first();
        $this->assertSame('Declaração adicional', $current->documentName());
        $this->assertSame(DocumentStatus::Replaced, $document->fresh()->status);
        $workflow->review($current, $this->actor, DocumentStatus::Approved);
        $workflow->approve($request, $this->actor);
        $this->assertNull($request->fresh()->registration_id);
        $this->get(route('bc-registration.show', $request))->assertOk()->assertSee('Declaração adicional')->assertDontSee('Nome alterado');
    }

    public function test_presential_custom_category_does_not_require_file(): void
    {
        $request = $this->release();
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'PRESENCIAL']);
        $this->post(route('bc-registration.receive-in-person', $request), ['types' => ['DECLARACAO_ADICIONAL']])->assertRedirect()->assertSessionHasNoErrors();
        $document = $request->documents()->first();
        $this->assertNotNull($document->received_at);
        $this->assertNull($document->path);
    }

    public function test_unrequested_code_and_invalid_catalog_code_are_rejected(): void
    {
        $request = $this->release();
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE']);
        $this->post('/matricula-digital/documentos', ['document_type' => 'NAO_SOLICITADO', 'document' => UploadedFile::fake()->image('x.png')])->assertRedirect()->assertSessionHasErrors();
        $this->assertSame(1, $request->documents()->count());
        $this->post('/bc/matriculas/configuracao/tipos', ['name' => 'Inválido', 'code' => '../arquivo'])->assertRedirect()->assertSessionHasErrors('code');
    }

    public function test_disabled_process_blocks_new_release_but_preserves_open_requests(): void
    {
        DB::table('processes')->where('id', $this->processId)->update(['document_workflow_enabled' => false]);
        $this->post('/bc/matriculas/pmd/'.$this->pmdId, ['school_class_id' => $this->original->school_class_id])->assertRedirect()->assertSessionHasErrors();
        $this->assertFalse(RegistrationRequest::where('pmd_preregistration_id', $this->pmdId)->exists());
        $this->post('/bc/matriculas/configuracao/processos/'.$this->processId, ['document_workflow_enabled' => '1',
            'documentation_deadline' => now()->addDays(7)->toDateTimeString(), 'documents' => [$this->typeId => ['enabled' => 1, 'required' => 1]]])->assertRedirect()->assertSessionHasNoErrors();
        $request = $this->release();
        DB::table('processes')->where('id', $this->processId)->update(['document_workflow_enabled' => false]);
        $this->assertSame($request->id, $this->release()->id);
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('processes', ['id' => $this->processId, 'documentation_configured_by' => $this->actor->id]);
    }

    public function test_legacy_compatibility_is_preserved_until_explicit_decision(): void
    {
        DB::table('processes')->where('id', $this->processId)->update(['document_workflow_enabled' => null]);
        $request = $this->release();
        $this->assertNotNull($request->id);
        DB::table('processes')->where('id', $this->processId)->update(['document_workflow_enabled' => true]);
        $this->post('/bc/matriculas/configuracao/processos/'.$this->processId, ['document_workflow_enabled' => 'legacy'])->assertStatus(422);
    }

    public function test_new_processes_require_activation_by_default(): void
    {
        $row = (array) DB::table('processes')->where('id', $this->processId)->first();
        unset($row['id'], $row['document_workflow_enabled']);
        $row['name'] = 'Processo novo de validação';
        $id = DB::table('processes')->insertGetId($row);
        $this->assertFalse(DB::table('processes')->where('id', $id)->value('document_workflow_enabled'));
    }
}

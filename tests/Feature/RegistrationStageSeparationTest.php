<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Mail\GuardianRegistrationNotice;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\NewPreRegistration;
use iEducar\Packages\PreMatricula\Models\Field;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Models\ProcessField;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationStageSeparationTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $original;

    private LegacyUser $actor;

    private int $pmdId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));
        Mail::fake();
        Storage::fake('registration-documents');
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $this->original = RegistrationRequest::query()->where('protocol', 'BC-2026-0008')->firstOrFail();
        $this->pmdId = $this->original->pmd_preregistration_id;
        $this->actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->original->update(['pmd_preregistration_id' => null]);
        DB::table('preregistrations')->where('id', $this->pmdId)->update([
            'status' => PreRegistration::STATUS_WAITING, 'document_submission_method' => null,
            'documentation_status' => null, 'documentation_deadline' => now()->addDays(7),
        ]);
        DB::table('preregistration_documents')->where('preregistration_id', $this->pmdId)->delete();
        $pmd = DB::table('preregistrations')->where('id', $this->pmdId)->first();
        DB::table('process_document_types')->where('process_id', $pmd->process_id)->delete();
        $type = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Certidão de nascimento', 'code' => 'CERTIDAO_NASCIMENTO', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert([
            'process_id' => $pmd->process_id, 'document_type_id' => $type,
            'required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_finalization_imports_formatted_cpf_for_new_student_and_guardian(): void
    {
        $this->assertNewPeopleImport(true);
    }

    public function test_only_finalization_imports_new_people_without_cpf(): void
    {
        $this->assertNewPeopleImport(false);
    }

    private function assertNewPeopleImport(bool $withCpf): void
    {
        $pmd = PreRegistration::query()->findOrFail($this->pmdId);
        $expected = [];
        foreach (['student_id' => $pmd->student, 'responsible_id' => $pmd->responsible] as $field => $source) {
            $digits = '0' . random_int(1000000000, 9999999999);
            $masked = substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9);
            $person = $source->replicate();
            $person->name = 'CPF regression ' . Str::uuid();
            $person->external_person_id = null;
            $person->cpf = $withCpf ? $masked : null;
            $person->rg = null;
            $person->birth_certificate = null;
            $person->saveOrFail();
            DB::table('preregistrations')->where('id', $this->pmdId)->update([$field => $person->getKey()]);
            $expected[$field] = $withCpf ? $digits : null;
        }

        $beforePeople = DB::table('cadastro.pessoa')->count();
        $beforeStudents = DB::table('pmieducar.aluno')->count();
        $request = $this->release();
        $this->assertSame($beforePeople, DB::table('cadastro.pessoa')->count());
        $this->assertSame($beforeStudents, DB::table('pmieducar.aluno')->count());
        $this->assertNull($request->student_id);
        $this->assertNull($request->guardian_id);
        $this->get(route('bc-registration.show', $request))->assertOk()->assertSee($request->studentName());
        $this->assertSame('AGUARDANDO_DOCUMENTOS', $request->status->value);
        $this->assertNull($request->registration_id);
        $request->update(['attendance_mode' => AttendanceMode::Online]);
        $request->documents()->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $request->schoolClass->update(['max_aluno' => 100]);
        $workflow = app(RegistrationWorkflow::class);
        app(DeclaredStudentData::class)->review($request, $this->actor, true, 'Cadastro nativo conferido', [
            'native_student_person_id' => $request->preregistration->student->external_person_id ? $this->original->student->ref_idpes : null,
            'native_guardian_person_id' => $request->preregistration->responsible->external_person_id ? $this->original->guardian_id : null,
        ]);
        $workflow->approve($request, $this->actor);
        $this->assertSame($beforePeople, DB::table('cadastro.pessoa')->count());
        $this->assertSame($beforeStudents, DB::table('pmieducar.aluno')->count());
        $registration = $workflow->finalize($request, $this->actor);
        $request->refresh();
        $this->assertNotNull($request->student_id);
        $this->assertSame($registration->id, $workflow->finalize($request, $this->actor)->id);
        foreach (['student_id' => $request->student->person->individual, 'responsible_id' => $request->guardian] as $field => $individual) {
            $actual = $individual->fresh()->getRawOriginal('cpf');
            if ($expected[$field] === null) {
                $this->assertNull($actual);
            } else {
                $this->assertSame($expected[$field], str_pad((string) $actual, 11, '0', STR_PAD_LEFT));
            }
        }
    }

    public function test_total_delivery_days_are_fixed_at_deferment_and_shared_by_both_modes(): void
    {
        $processId = DB::table('preregistrations')->where('id', $this->pmdId)->value('process_id');
        DB::table('processes')->where('id', $processId)->update(['documentation_delivery_days' => 5]);
        $request = $this->release();
        $deadline = now()->addDays(5)->endOfDay();
        $this->assertTrue($request->document_deadline->equalTo($deadline->copy()->startOfSecond()));
        foreach (AttendanceMode::cases() as $mode) {
            app(RegistrationWorkflow::class)->changeGuardianMode($request->fresh(), $this->pmdId, $mode);
            $this->assertTrue($request->fresh()->document_deadline->equalTo($deadline->copy()->startOfSecond()));
        }
        DB::table('processes')->where('id', $processId)->update(['documentation_delivery_days' => 15]);
        $this->assertTrue($request->fresh()->document_deadline->equalTo($deadline->copy()->startOfSecond()));
    }

    private function release(): RegistrationRequest
    {
        $this->actingAs($this->actor);
        app(AcceptPreRegistrations::class)(null, [
            'ids' => [$this->pmdId], 'classroom' => $this->original->school_class_id,
        ]);

        // Exercise historical version 1; version 2 transitions have their own integration tests.
        $request = RegistrationRequest::query()->where('pmd_preregistration_id', $this->pmdId)->firstOrFail();
        $request->update(['workflow_version' => 1]);

        return $request;
    }

    public function test_waiting_intake_blocks_mode_upload_and_progress(): void
    {
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->get('/matricula-digital')->assertOk()->assertSee('Pré-matrícula em análise')
            ->assertSee('Disponível após o deferimento')->assertDontSee('name="document"', false);
        $this->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertUnprocessable();
        $this->postJson('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('certidao.png')])->assertUnprocessable();
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());
    }

    public function test_existing_bc_link_does_not_bypass_waiting_intake(): void
    {
        $this->original->update(['pmd_preregistration_id' => $this->pmdId,
            'status' => 'AGUARDANDO_DOCUMENTOS', 'approved_at' => null, 'document_deadline' => now()->addDay()]);
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertUnprocessable();
        $this->actingAs($this->actor);
        $document = $this->original->documents()->firstOrFail();
        $document->update(['status' => DocumentStatus::Sent, 'received_at' => now()]);
        $this->postJson(route('bc-registration.review', $document), ['status' => 'APROVADO'])->assertUnprocessable();
        $this->postJson(route('bc-registration.receive', $this->original), [
            'document_type' => $document->document_type->value,
            'document' => UploadedFile::fake()->image('bloqueado.png'),
        ])->assertUnprocessable();
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());

        $this->postJson(route('bc-registration.action', $this->original), ['action' => 'approve'])->assertUnprocessable();
        $this->original->update(['status' => 'APROVADA', 'approved_at' => now()]);
        $this->postJson(route('bc-registration.action', $this->original), ['action' => 'finalize'])->assertUnprocessable();
        $this->get(route('bc-registration.index'))->assertOk()->assertDontSee(route('bc-registration.show', $this->original), false);
    }

    public function test_release_is_idempotent_and_does_not_choose_mode_or_create_enrollment(): void
    {
        $before = DB::table('pmieducar.matricula')->count();
        $request = $this->release();
        $this->release();
        $this->assertNull($request->attendance_mode);
        $this->assertNull($request->registration_id);
        $this->assertDatabaseHas('preregistrations', ['id' => $this->pmdId, 'status' => PreRegistration::STATUS_SUMMONED,
            'document_submission_method' => null]);
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
        foreach ([EventType::PreRegistrationApproved, EventType::DocumentsReleased] as $event) {
            $this->assertSame(1, $request->events()->where('event', $event->value)->count());
        }
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId])->get('/matricula-digital')
            ->assertOk()->assertSee('Pré-matrícula deferida')->assertSee('Aguardando escolha da modalidade')
            ->assertDontSee('name="document"', false);
        $this->postJson('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('sem-escolha.png')])->assertUnprocessable();
        $this->postJson(route('bc-registration.action', $request), ['action' => 'approve'])->assertUnprocessable();
    }

    public function test_online_correction_approval_and_explicit_unique_enrollment(): void
    {
        $before = DB::table('pmieducar.matricula')->count();
        $request = $this->release();
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertRedirect();
        $this->assertSame(AttendanceMode::Online, $request->fresh()->attendance_mode);
        $this->get('/matricula-digital')->assertOk()->assertSee('name="document"', false);
        $this->post('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('primeiro.png')])->assertRedirect();
        $document = $request->documents()->firstOrFail();
        $workflow = app(RegistrationWorkflow::class);
        $workflow->review($document, $this->actor, DocumentStatus::Correction, 'Ilegível');
        $this->get('/matricula-digital')->assertOk()->assertSee('1 com correção solicitada');
        $this->post('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO',
            'replaces_id' => $document->id, 'document' => UploadedFile::fake()->image('corrigido.png')])->assertRedirect();
        $replacement = $request->documents()->where('replaces_id', $document->id)->firstOrFail();
        $this->assertSame(DocumentStatus::Replaced, $document->fresh()->status);
        $workflow->review($replacement, $this->actor, DocumentStatus::Approved);
        app(DeclaredStudentData::class)->review($request, $this->actor, true, 'Cadastro nativo conferido', [
            'native_student_person_id' => $request->preregistration->student->external_person_id ? $this->original->student->ref_idpes : null,
            'native_guardian_person_id' => $request->preregistration->responsible->external_person_id ? $this->original->guardian_id : null,
        ]);
        $workflow->approve($request, $this->actor);
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
        $this->assertNull($request->fresh()->registration_id);
        $this->get('/matricula-digital')->assertOk()->assertSee('Documentação aprovada')
            ->assertSee('Aguardando efetivação pela unidade escolar');
        $this->get(route('bc-registration.show', $request))->assertOk()->assertSee('value="approve"', false);
        $registration = $workflow->finalize($request, $this->actor);
        $again = $workflow->finalize($request, $this->actor);
        $this->assertSame($registration->getKey(), $again->getKey());
        $this->assertSame($before + 1, DB::table('pmieducar.matricula')->count());
        $this->assertSame(1, DB::table('pmieducar.matricula_turma')->where('ref_cod_matricula', $registration->getKey())->where('ativo', 1)->count());
        $this->get('/matricula-digital')->assertOk()->assertSee('Matrícula efetivada');
    }

    public function test_presential_choice_requires_no_upload_and_school_records_receipt(): void
    {
        $request = $this->release();
        $deadline = $request->document_deadline->toDateTimeString();
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'PRESENCIAL'])->assertRedirect();
        $this->get('/matricula-digital')->assertOk()->assertSee('Apresente os documentos necessários')
            ->assertDontSee('name="document"', false);
        $this->post(route('bc-registration.receive-in-person', $request), ['types' => ['CERTIDAO_NASCIMENTO']])->assertRedirect();
        $document = $request->documents()->firstOrFail();
        $this->assertNull($document->path);
        $this->assertNotNull($document->received_at);
        $this->assertSame($deadline, $request->fresh()->document_deadline->toDateTimeString());
        $event = $request->events()->where('event', EventType::Received->value)->firstOrFail();
        $this->assertSame($this->actor->getKey(), $event->actor_id);
    }

    public function test_incomplete_documentation_and_other_school_are_blocked(): void
    {
        $request = $this->release();
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId])->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE']);
        $this->postJson(route('bc-registration.action', $request), ['action' => 'approve'])->assertUnprocessable();
        $this->get(route('bc-registration.show', $request))->assertOk()->assertDontSee('value="finalize"', false);
        $other = LegacyUser::query()->whereIn('cod_usuario', BcDemoEntity::query()->where('key', 'like', 'user.operador.%')->select('legacy_id'))
            ->get()->first(fn ($actor) => !RegistrationRequest::query()->visibleTo($actor)->whereKey($request->id)->exists());
        $this->assertNotNull($other);
        $document = $request->documents()->firstOrFail();
        $this->actingAs($other)->postJson(route('bc-registration.review', $document), ['status' => 'APROVADO'])->assertForbidden();
        $this->postJson(route('bc-registration.action', $request), ['action' => 'approve'])->assertForbidden();
        $this->postJson(route('bc-registration.action', $request), ['action' => 'finalize'])->assertForbidden();
    }

    public function test_document_choice_is_not_a_public_process_field_or_intake_argument(): void
    {
        $field = Field::query()->where('internal', 'bc_attendance_mode')->firstOrFail();
        $pmd = PreRegistration::query()->findOrFail($this->pmdId);
        $this->assertFalse(ProcessField::query()->where('process_id', $pmd->process_id)->where('field_id', $field->id)->exists());
        $this->actingAs($this->actor)->postJson('/bc/matriculas/pmd/' . $this->pmdId, [
            'school_class_id' => $this->original->school_class_id, 'attendance_mode' => 'ONLINE',
        ])->assertStatus(405);
        $this->expectException(ValidationException::class);
        app(NewPreRegistration::class)(null, ['student' => [['field' => 'field_' . $field->id, 'value' => 'ONLINE']]]);
    }

    public function test_triage_columns_and_navigation_are_separate(): void
    {
        $request = $this->release();
        $this->get(route('bc-registration.index'))->assertOk()->assertSee('Triagem Documental')
            ->assertSee('Data do deferimento')->assertSee('Última atualização')
            ->assertSee('Aguardando escolha da modalidade')->assertSee(route('bc-registration.show', $request), false);
        $this->get(route('bc-registration.intake'))->assertRedirect('/pre-matricula-digital/inscricoes');
        $this->get(route('bc-registration.enrollments'))->assertOk()->assertSee('Matrículas efetivadas')
            ->assertDontSee(route('bc-registration.show', $request), false)
            ->assertSee('only_enrolled=1', false);
    }

    public function test_documentary_notices_use_existing_queue_once_per_real_event(): void
    {
        $request = $this->release();
        $request->update(['seed_source' => null]);
        $workflow = app(RegistrationWorkflow::class);
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE']);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE']);
        $this->post('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('certidao.png')]);
        $document = $request->documents()->firstOrFail();
        $workflow->review($document, $this->actor, DocumentStatus::Approved);
        app(DeclaredStudentData::class)->review($request, $this->actor, true, 'Cadastro nativo conferido', [
            'native_student_person_id' => $request->preregistration->student->external_person_id ? $this->original->student->ref_idpes : null,
            'native_guardian_person_id' => $request->preregistration->responsible->external_person_id ? $this->original->guardian_id : null,
        ]);
        $workflow->approve($request, $this->actor);
        $workflow->approve($request, $this->actor);
        foreach (['DOCUMENTS_OPEN', 'MODE_SELECTED', 'DOCUMENT_RECEIVED', 'DOCUMENTATION_APPROVED'] as $kind) {
            $this->assertSame(1, DB::table('bc_guardian_notifications')->where('kind', $kind)->count());
        }
        config(['bc-notifications.enabled' => true]);
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, fn ($mail) => $mail->kind === 'DOCUMENTATION_APPROVED'
            && str_contains($mail->render(), 'A matrícula ainda não existe'));
        $before = DB::table('bc_guardian_notifications')->whereNotNull('sent_at')->count();
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        $this->assertSame($before, DB::table('bc_guardian_notifications')->whereNotNull('sent_at')->count());
    }

    public function test_registered_preregistration_queues_notice_without_creating_documentary_stage(): void
    {
        $row = (array) DB::table('preregistrations')->where('id', $this->pmdId)->first();
        $process = (array) DB::table('processes')->where('id', $row['process_id'])->first();
        unset($process['id']);
        $process['name'] = 'Processo isolado ' . Str::random(8);
        $row['process_id'] = DB::table('processes')->insertGetId($process);
        unset($row['id']);
        $row['protocol'] = 'TEST-' . Str::upper(Str::random(10));
        $new = PreRegistration::query()->create($row);
        $this->assertFalse(RegistrationRequest::query()->where('pmd_preregistration_id', $new->id)->exists());
        $this->assertSame(1, DB::table('bc_guardian_notifications')->where('kind', 'PREREGISTRATION_REGISTERED')->count());
        config(['bc-notifications.enabled' => true]);
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, fn ($mail) => $mail->kind === 'PREREGISTRATION_REGISTERED');
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_rollback_refuses_to_destroy_pending_choice_state(): void
    {
        $this->release();
        $migration = require database_path('migrations/2026_10_05_180000_separate_document_choice_and_notice_origin.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_rejected_preregistration_cannot_release_existing_documentary_link(): void
    {
        $request = $this->release();
        DB::table('preregistrations')->where('id', $this->pmdId)->update(['status' => PreRegistration::STATUS_REJECTED]);
        $this->expectException(ValidationException::class);
        $this->release();
    }

    public function test_closed_documentary_request_cannot_be_released_silently(): void
    {
        $request = $this->release();
        $request->update(['status' => RequestStatus::Expired]);
        DB::table('preregistrations')->where('id', $this->pmdId)->update(['status' => PreRegistration::STATUS_WAITING]);
        $events = $request->events()->count();
        try {
            $this->release();
            $this->fail('O deferimento de solicitação encerrada deveria ser recusado.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('encerrada', $error->errors()['pmd'][0]);
        }
        $this->assertSame(PreRegistration::STATUS_WAITING, DB::table('preregistrations')->where('id', $this->pmdId)->value('status'));
        $this->assertSame($events, $request->events()->count());
    }
}

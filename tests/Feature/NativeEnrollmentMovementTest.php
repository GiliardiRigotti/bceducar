<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Models\BcDemoEntity;
use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\LegacyUserSchool;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Database\Factories\LegacySchoolClassFactory;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Exceptions\EnrollmentRelocationValidationException;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NativeEnrollmentMovementTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $request;

    private LegacyUser $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));
        Mail::fake();
        if (!BcDemoEntity::where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $this->actor = LegacyUser::findOrFail(BcDemoEntity::where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($this->actor);
        $this->request = RegistrationRequest::where('protocol', 'BC-2026-0008')->firstOrFail();
        $this->request->update(['status' => RequestStatus::Approved, 'approved_at' => now(),
            'attendance_mode' => AttendanceMode::InPerson, 'document_deadline' => now()->addDays(5)]);
        $this->request->documents()->update(['status' => DocumentStatus::Approved->value,
            'received_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $this->actor->getKey()]);
        DB::table('preregistrations')->where('id', $this->request->pmd_preregistration_id)
            ->update(['status' => PreRegistration::STATUS_IN_CONFIRMATION]);
        config(['prematricula.features.allow_transfer_registration' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function previous(bool $sameSchool, bool $sameClass = false): array
    {
        $destination = LegacySchoolClass::findOrFail($this->request->school_class_id);
        $class = $sameClass ? $destination : ($sameSchool
            ? LegacySchoolClassFactory::new()->create([
                'ref_ref_cod_escola' => $destination->school_id, 'ref_ref_cod_serie' => $destination->grade_id,
                'ref_cod_curso' => $destination->course_id, 'ref_cod_instituicao' => $destination->ref_cod_instituicao,
                'turma_turno_id' => $destination->turma_turno_id, 'ano' => $destination->ano,
                'ref_usuario_cad' => $this->actor->getKey(),
            ])
            : LegacySchoolClass::where('ref_ref_cod_serie', $destination->grade_id)
                ->where('ref_ref_cod_escola', '!=', $destination->school_id)->where('ano', $destination->ano)->firstOrFail());
        $registration = LegacyRegistration::create([
            'ref_cod_aluno' => $this->request->student_id, 'ref_ref_cod_escola' => $class->school_id,
            'ref_ref_cod_serie' => $class->grade_id, 'ref_cod_curso' => $class->course_id,
            'ano' => $this->request->school_year, 'ref_usuario_cad' => $this->actor->getKey(),
            'aprovado' => \App_Model_MatriculaSituacao::EM_ANDAMENTO, 'ativo' => 1,
            'ultima_matricula' => 1, 'data_matricula' => '2026-09-01',
        ]);
        $enrollment = new LegacyEnrollment;
        $enrollment->forceFill(['ref_cod_matricula' => $registration->getKey(), 'ref_cod_turma' => $class->getKey(),
            'sequencial' => 1, 'ref_usuario_cad' => $this->actor->getKey(), 'ativo' => 1,
            'turno_id' => $class->turma_turno_id, 'data_enturmacao' => '2026-09-01', 'transferido' => false, 'remanejado' => false])->save();

        return [$registration, $enrollment];
    }

    public function test_native_transfer_closes_previous_enrollment_and_is_idempotent(): void
    {
        [$previous, $enrollment] = $this->previous(false);
        $result = app(RegistrationWorkflow::class)->finalize($this->request, $this->actor);
        $this->assertNotSame($previous->getKey(), $result->getKey());
        $this->assertSame(\App_Model_MatriculaSituacao::TRANSFERIDO, $previous->fresh()->aprovado);
        $this->assertTrue($enrollment->fresh()->transferido);
        $this->assertSame(1, $result->fresh()->ultima_matricula);
        $this->assertSame(0, $previous->fresh()->ultima_matricula);
        $this->assertSame($result->getKey(), app(RegistrationWorkflow::class)->finalize($this->request, $this->actor)->getKey());
        $this->assertSame(1, $result->activeEnrollments()->count());
        $metadata = json_decode(DB::table('bc_registration_events')->where('registration_request_id', $this->request->id)
            ->where('event', 'REGISTRATION_CREATED')->orderByDesc('id')->value('metadata'), true);
        $this->assertSame('TRANSFER', $metadata['movement']);
        $this->assertSame($previous->getKey(), $metadata['previous_registration_id']);
        $this->assertSame(PreRegistration::STATUS_ACCEPTED, PreRegistration::findOrFail($this->request->pmd_preregistration_id)->status);
        $this->assertDatabaseHas('bc_registration_events', ['registration_request_id' => $this->request->id,
            'event' => 'REGISTRATION_CREATED']);
    }

    public function test_native_relocation_reuses_registration_and_preserves_last_registration(): void
    {
        [$previous, $enrollment] = $this->previous(true);
        $result = app(RegistrationWorkflow::class)->finalize($this->request, $this->actor);
        $this->assertSame($previous->getKey(), $result->getKey());
        $this->assertTrue($enrollment->fresh()->remanejado);
        $this->assertSame(1, $result->fresh()->ultima_matricula);
        $this->assertSame(1, $result->activeEnrollments()->count());
        $this->assertSame($this->request->school_class_id, $result->activeEnrollments()->firstOrFail()->ref_cod_turma);
        $this->assertSame($result->getKey(), app(RegistrationWorkflow::class)->finalize($this->request, $this->actor)->getKey());
    }

    public function test_disabled_flag_preserves_existing_registration_and_documentary_state(): void
    {
        [$previous, $enrollment] = $this->previous(false);
        config(['prematricula.features.allow_transfer_registration' => false]);
        $this->assertRefused();
        $this->assertSame(\App_Model_MatriculaSituacao::EM_ANDAMENTO, $previous->fresh()->aprovado);
        $this->assertFalse($enrollment->fresh()->transferido);
        $this->assertSame(1, $previous->fresh()->ultima_matricula);
    }

    public function test_multiple_active_registrations_are_not_silently_transferred(): void
    {
        $this->previous(false);
        $this->previous(true);
        $this->assertRefused();
    }

    public function test_same_class_failure_rolls_back_native_cancellation(): void
    {
        [$previous, $enrollment] = $this->previous(true, true);
        $before = $enrollment->fresh()->getAttributes();
        try {
            app(RegistrationWorkflow::class)->finalize($this->request, $this->actor);
            $this->fail('The native service must refuse the same classroom.');
        } catch (EnrollmentRelocationValidationException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertSame($before, $enrollment->fresh()->getAttributes());
        $this->assertSame(1, $previous->fresh()->ultima_matricula);
        $this->assertSame(RequestStatus::Approved, $this->request->fresh()->status);
    }

    public function test_full_destination_class_refuses_movement_without_changing_previous_enrollment(): void
    {
        [$previous, $enrollment] = $this->previous(false);
        LegacySchoolClass::findOrFail($this->request->school_class_id)->update(['max_aluno' => 0]);
        $this->assertRefused();
        $this->assertSame(1, $previous->fresh()->ultima_matricula);
        $this->assertFalse($enrollment->fresh()->transferido);
    }

    public function test_different_grade_and_unlinked_legacy_request_remain_blocked(): void
    {
        [$previous] = $this->previous(false);
        $originalLink = $this->request->pmd_preregistration_id;
        $this->request->update(['pmd_preregistration_id' => null]);
        $this->assertRefused();
        $this->request->update(['pmd_preregistration_id' => $originalLink]);
        $grade = DB::table('pmieducar.serie')->where('cod_serie', '!=', $this->request->grade_id)->value('cod_serie');
        $previous->update(['ref_ref_cod_serie' => $grade]);
        $this->assertRefused();
    }

    public function test_destination_operator_cannot_change_an_unauthorized_origin_school(): void
    {
        [$previous, $enrollment] = $this->previous(false);
        $ids = LegacyUserSchool::where('ref_cod_escola', $this->request->school_id)->pluck('ref_cod_usuario');
        $operator = LegacyUser::whereIn((new LegacyUser)->getKeyName(), $ids)
            ->get()->first(fn ($user) => $user->isSchooling());
        $this->assertNotNull($operator);
        $this->actingAs($operator);
        try {
            app(RegistrationWorkflow::class)->finalize($this->request, $operator);
            $this->fail('The origin school must also be authorized.');
        } catch (AuthorizationException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertSame(\App_Model_MatriculaSituacao::EM_ANDAMENTO, $previous->fresh()->aprovado);
        $this->assertFalse($enrollment->fresh()->transferido);
        $this->assertSame(RequestStatus::Approved, $this->request->fresh()->status);
    }

    private function assertRefused(): void
    {
        $before = LegacyRegistration::count();
        try {
            app(RegistrationWorkflow::class)->finalize($this->request, $this->actor);
            $this->fail('Movement must be refused.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame($before, LegacyRegistration::count());
        $this->assertSame(RequestStatus::Approved, $this->request->fresh()->status);
        $this->assertNull($this->request->fresh()->registration_id);
    }
}

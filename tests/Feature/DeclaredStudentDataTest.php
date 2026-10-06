<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\GuardianProfiles;
use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeclaredStudentDataTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $application;

    private LegacyUser $actor;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $source = RegistrationRequest::query()->where('protocol', 'BC-2026-0008')->firstOrFail();
        $id = $source->pmd_preregistration_id;
        $source->update(['pmd_preregistration_id' => null]);
        DB::table('preregistrations')->where('id', $id)->update([
            'status' => PreRegistration::STATUS_WAITING, 'documentation_status' => null,
            'document_submission_method' => null, 'documentation_deadline' => now()->addDays(7),
        ]);
        $this->actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->application = app(PmdIntake::class)->import($id, $source->school_class_id, $this->actor);
        // Preserve coverage of the pre-existing finalization workflow.
        $this->application->update(['workflow_version' => 1]);
        $this->profileId = app(GuardianProfiles::class)->claim($id);
        $this->withSession(['bc_guardian_profile_id' => $this->profileId, 'bc_guardian_pmd_id' => $id]);
    }

    public function test_correction_clones_declaration_preserves_native_data_and_requires_new_review(): void
    {
        $pmd = $this->application->preregistration;
        $originalId = $pmd->student_id;
        $originalName = $pmd->student->name;
        $student = ['name' => $originalName . ' Correção', 'date_of_birth' => '2014-05-10'];
        $this->get('/matricula-digital/ficha')->assertOk()->assertSee('Dados declarados da pré-matrícula');
        $this->postJson('/matricula-digital/ficha', ['student' => $student, 'reason' => 'Correção do nome'])->assertUnprocessable();
        $this->actingAs($this->actor)->post(route('bc-registration.data.review', $this->application), [
            'decision' => 'CORRECTION', 'reason' => 'Nome divergente do comprovante',
        ])->assertRedirect();
        $this->get('/matricula-digital')->assertOk()->assertSee('Nome divergente do comprovante');
        $people = DB::table('cadastro.pessoa')->count();
        $this->post('/matricula-digital/ficha', ['student' => $student, 'reason' => 'Conferido na certidão'])->assertRedirect()->assertSessionHasNoErrors();
        $pmd->refresh();
        $this->assertNotSame($originalId, $pmd->student_id);
        $this->assertSame($originalName, DB::table('people')->where('id', $originalId)->value('name'));
        $this->assertSame($people, DB::table('cadastro.pessoa')->count());
        $this->assertSame('PENDING', app(DeclaredStudentData::class)->reviewFor($pmd->id)->status);
        $this->assertDatabaseHas('bc_declared_data_events', ['pmd_id' => $pmd->id, 'event' => 'DECLARED_DATA_CHANGED', 'actor_id' => $this->profileId]);
        $this->application->update(['attendance_mode' => AttendanceMode::Online]);
        $this->application->documents()->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $this->postJson(route('bc-registration.action', $this->application), ['action' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('declared_data');
        $this->post(route('bc-registration.data.review', $this->application), ['decision' => 'APPROVED'])->assertRedirect();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'approve'])->assertRedirect();
        $this->assertNull($this->application->fresh()->student_id);
        $this->get(route('bc-registration.show', $this->application))->assertOk()->assertSee('Reabrir conferência e solicitar correção')->assertSee('Histórico da ficha cadastral');
        $this->postJson('/matricula-digital/ficha', ['student' => $student, 'reason' => 'Outra alteração'])->assertUnprocessable();
        $deadline = $this->application->fresh()->document_deadline->toDateTimeString();
        $this->post(route('bc-registration.data.review', $this->application), [
            'decision' => 'CORRECTION', 'reason' => 'Telefone precisa de DDD',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->application->fresh()->approved_at);
        $this->assertSame($deadline, $this->application->fresh()->document_deadline->toDateTimeString());
        $student['phone'] = '(47) 3333-1234';
        $this->post('/matricula-digital/ficha', ['student' => $student, 'reason' => 'DDD corrigido'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('PENDING', app(DeclaredStudentData::class)->reviewFor($pmd->id)->status);
    }

    public function test_other_profile_and_school_cannot_change_or_review_the_declaration(): void
    {
        $other = PreRegistration::query()->where('responsible_id', '!=', $this->application->preregistration->responsible_id)->firstOrFail();
        $otherProfile = app(GuardianProfiles::class)->claim($other->id);
        $this->withSession(['bc_guardian_profile_id' => $otherProfile]);
        $this->get('/matricula-digital/ficha')->assertForbidden();
        $this->postJson('/matricula-digital/ficha', ['student' => ['name' => 'Ataque', 'date_of_birth' => '2014-01-01'], 'reason' => 'Ataque'])->assertForbidden();
        $operator = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.operador.taquaras')->value('legacy_id'));
        $this->actingAs($operator)->postJson(route('bc-registration.data.review', $this->application), ['decision' => 'APPROVED'])->assertForbidden();
    }

    public function test_full_class_does_not_create_native_people_or_students(): void
    {
        $this->application->update(['attendance_mode' => AttendanceMode::Online]);
        $this->application->documents()->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        app(DeclaredStudentData::class)->review($this->application, $this->actor, true, null);
        app(RegistrationWorkflow::class)->approve($this->application, $this->actor);
        $this->application->schoolClass->update(['max_aluno' => 0]);
        $people = DB::table('cadastro.pessoa')->count();
        $students = DB::table('pmieducar.aluno')->count();
        $this->actingAs($this->actor)->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertUnprocessable();
        $this->assertSame($people, DB::table('cadastro.pessoa')->count());
        $this->assertSame($students, DB::table('pmieducar.aluno')->count());
        $this->assertNull($this->application->fresh()->student_id);
        $this->assertNull($this->application->fresh()->registration_id);
    }

    public function test_uncertain_identity_rolls_back_until_explicit_scoped_confirmation(): void
    {
        $source = RegistrationRequest::query()->where('protocol', 'BC-2026-0008')->firstOrFail();
        $pmd = $this->application->preregistration;
        $pmd->student->update(['cpf' => null, 'external_person_id' => $source->student->ref_idpes]);
        $this->application->update(['attendance_mode' => AttendanceMode::Online]);
        $this->application->documents()->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $this->application->schoolClass->update(['max_aluno' => 100]);
        app(DeclaredStudentData::class)->review($this->application, $this->actor, true, null);
        app(RegistrationWorkflow::class)->approve($this->application, $this->actor);
        $people = DB::table('cadastro.pessoa')->count();
        $students = DB::table('pmieducar.aluno')->count();
        $this->actingAs($this->actor)->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity');
        $this->assertSame($people, DB::table('cadastro.pessoa')->count());
        $this->assertSame($students, DB::table('pmieducar.aluno')->count());
        $this->assertNull($this->application->fresh()->student_id);
        $this->assertFalse($this->application->events()->where('event', 'DECLARED_DATA_CONSOLIDATED')->exists());
        $this->post(route('bc-registration.data.review', $this->application), [
            'decision' => 'APPROVED', 'reason' => 'Identidade conferida no cadastro nativo e comprovantes',
            'native_student_person_id' => $source->student->ref_idpes,
            'native_guardian_person_id' => $source->guardian_id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($people, DB::table('cadastro.pessoa')->count());
        $this->assertSame($students, DB::table('pmieducar.aluno')->count());
        $registrationId = $this->application->fresh()->registration_id;
        $this->assertNotNull($registrationId);
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect();
        $this->assertSame($registrationId, $this->application->fresh()->registration_id);
        $this->postJson(route('bc-registration.data.review', $this->application), [
            'decision' => 'CORRECTION', 'reason' => 'Tentativa após matrícula',
        ])->assertUnprocessable();
        $this->assertSame($registrationId, $this->application->fresh()->registration_id);
    }
}

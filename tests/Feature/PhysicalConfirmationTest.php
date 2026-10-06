<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\GuardianProfiles;
use App\EnrollmentRequests\PhysicalConfirmation;
use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Mail\GuardianRegistrationNotice;
use App\Models\BcDemoEntity;
use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhysicalConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $application;

    private LegacyUser $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));
        Mail::fake();
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $source = RegistrationRequest::query()->where('protocol', 'BC-2026-0008')->firstOrFail();
        $pmd = $source->preregistration;
        $source->update(['pmd_preregistration_id' => null]);
        foreach (['student_id' => $pmd->student, 'responsible_id' => $pmd->responsible] as $field => $snapshot) {
            $person = $snapshot->replicate();
            $person->name = 'Conferência V2 ' . Str::uuid();
            $person->cpf = null;
            $person->rg = null;
            $person->birth_certificate = null;
            $person->external_person_id = null;
            $person->saveOrFail();
            DB::table('preregistrations')->where('id', $pmd->id)->update([$field => $person->id]);
        }
        DB::table('preregistrations')->where('id', $pmd->id)->update([
            'status' => PreRegistration::STATUS_WAITING, 'documentation_status' => null,
            'document_submission_method' => null, 'documentation_deadline' => now()->addDays(7), 'external_person_id' => null,
        ]);
        DB::table('preregistration_documents')->where('preregistration_id', $pmd->id)->delete();
        $this->actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->application = app(PmdIntake::class)->import($pmd->id, $source->school_class_id, $this->actor);
        $this->application->update(['attendance_mode' => AttendanceMode::Online]);
        $this->application->documents()->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $this->application->schoolClass->update(['max_aluno' => 100]);
        app(DeclaredStudentData::class)->review($this->application, $this->actor, true, null);
        $this->actingAs($this->actor);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function approve(): void
    {
        app(RegistrationWorkflow::class)->approve($this->application, $this->actor);
        $this->application->refresh();
        $this->assertSame('INTEGRATED', $this->application->integration_status);
    }

    private function reviewAll(): void
    {
        $service = app(PhysicalConfirmation::class);
        foreach ($this->application->documents as $document) {
            $service->review($this->application, $this->actor, 'document:' . $document->id, true, null);
        }
        $service->review($this->application, $this->actor, 'cadastro', true, null);
    }

    public function test_digital_approval_integrates_without_enrollment_and_physical_confirmation_promotes_once(): void
    {
        $people = DB::table('cadastro.pessoa')->count();
        $this->approve();
        $this->assertSame(2, $this->application->workflow_version);
        $this->assertSame($people + 2, DB::table('cadastro.pessoa')->count());
        $id = $this->application->intermediate_registration_id;
        $this->assertSame(11, LegacyRegistration::findOrFail($id)->aprovado);
        $this->assertFalse(LegacyEnrollment::where('ref_cod_matricula', $id)->exists());
        $this->assertNull($this->application->registration_id);
        $this->assertSame(PreRegistration::STATUS_IN_CONFIRMATION, $this->application->preregistration->status);
        $this->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertUnprocessable();
        $this->get(route('bc-registration.show', $this->application))->assertOk()->assertSee('Original apresentado e conferido');
        $this->approve();
        $this->assertSame($id, $this->application->intermediate_registration_id);
        $this->assertSame($people + 2, DB::table('cadastro.pessoa')->count());
        $this->reviewAll();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
        $this->application->refresh();
        $this->assertSame($id, $this->application->registration_id);
        $this->assertSame(3, LegacyRegistration::findOrFail($id)->aprovado);
        $this->assertSame(PreRegistration::STATUS_ACCEPTED, $this->application->preregistration->status);
        $this->assertSame(1, LegacyEnrollment::where('ref_cod_matricula', $id)->where('ativo', 1)->count());
        $this->assertNotNull($this->application->physical_confirmed_at);
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->application->events()->where('event', 'PHYSICAL_DOCUMENTATION_CONFIRMED')->count());
    }

    public function test_individual_divergence_is_visible_and_blocks_confirmation_until_regularized(): void
    {
        $this->approve();
        $this->reviewAll();
        $document = $this->application->documents->first();
        $this->post(route('bc-registration.physical.review', $this->application), [
            'subject' => 'document:' . $document->id, 'decision' => 'PENDING', 'reason' => 'Original ilegível: apresente segunda via.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertUnprocessable();
        $this->assertSame(11, LegacyRegistration::findOrFail($this->application->intermediate_registration_id)->aprovado);
        $this->assertFalse(LegacyEnrollment::where('ref_cod_matricula', $this->application->intermediate_registration_id)->exists());
        $profile = app(GuardianProfiles::class)->claim($this->application->pmd_preregistration_id);
        $this->withSession(['bc_guardian_profile_id' => $profile, 'bc_guardian_pmd_id' => $this->application->pmd_preregistration_id]);
        $this->get('/matricula-digital')->assertOk()->assertSee('Original ilegível: apresente segunda via.')->assertSee('Regularizar até');
        $this->post(route('bc-registration.physical.review', $this->application), [
            'subject' => 'document:' . $document->id, 'decision' => 'APPROVED', 'reason' => 'Segunda via apresentada.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->application->events()->where('event', 'PHYSICAL_CORRECTION_REQUESTED')->count());
    }

    public function test_integration_failure_preserves_approval_and_retry_is_idempotent(): void
    {
        $pmd = $this->application->preregistration;
        $pmd->student->update(['external_person_id' => 999999999]);
        $people = DB::table('cadastro.pessoa')->count();
        app(RegistrationWorkflow::class)->approve($this->application, $this->actor);
        $this->application->refresh();
        $this->assertSame('ERROR', $this->application->integration_status);
        $this->assertSame(PreRegistration::STATUS_SUMMONED, $this->application->preregistration->status);
        $this->assertNotNull($this->application->approved_at);
        $this->assertSame('APROVADA', $this->application->status->value);
        $this->assertNull($this->application->student_id);
        $this->assertNull($this->application->registration_id);
        $this->assertSame($people, DB::table('cadastro.pessoa')->count());
        $event = $this->application->events()->where('event', 'NATIVE_INTEGRATION_FAILED')->firstOrFail();
        $this->assertEqualsCanonicalizing(['error_class', 'error_code', 'retryable'], array_keys($event->metadata));
        $this->assertSame('IDENTITY_REVIEW_REQUIRED', $event->metadata['error_code']);
        $this->get(route('bc-registration.show', $this->application))->assertOk()->assertSee('Identidade pendente');
        $pmd->student->update(['external_person_id' => null]);
        $this->approve();
        $this->approve();
        $this->assertSame(1, $this->application->events()->where('event', 'NATIVE_PREREGISTRATION_INTEGRATED')->count());
    }

    public function test_cadastral_divergence_reopens_guardian_correction_without_recreating_native_student(): void
    {
        $this->approve();
        $student = $this->application->student_id;
        $registration = $this->application->intermediate_registration_id;
        $this->post(route('bc-registration.physical.review', $this->application), [
            'subject' => 'cadastro', 'decision' => 'PENDING', 'reason' => 'Identificação divergente: corrija a ficha.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->application->refresh();
        $this->assertNull($this->application->approved_at);
        $this->assertSame('CORRECTION', app(DeclaredStudentData::class)->reviewFor($this->application->pmd_preregistration_id)->status);
        $profile = app(GuardianProfiles::class)->claim($this->application->pmd_preregistration_id);
        $this->withSession(['bc_guardian_profile_id' => $profile, 'bc_guardian_pmd_id' => $this->application->pmd_preregistration_id]);
        $this->get('/matricula-digital')->assertOk()->assertSee('Identificação divergente: corrija a ficha.')->assertSee('Regularizar até');
        app(DeclaredStudentData::class)->review($this->application, $this->actor, true, 'Ficha regularizada e cadastro nativo conferido.');
        $this->approve();
        $this->assertSame($student, $this->application->student_id);
        $this->assertSame($registration, $this->application->intermediate_registration_id);
        $this->reviewAll();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_full_class_rolls_back_native_promotion_and_preserves_intermediate_registration(): void
    {
        $this->approve();
        $this->reviewAll();
        $this->application->schoolClass->update(['max_aluno' => 0]);
        $this->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertUnprocessable();
        $this->assertSame(11, LegacyRegistration::findOrFail($this->application->intermediate_registration_id)->aprovado);
        $this->assertNull($this->application->fresh()->physical_confirmed_at);
        $this->assertNull($this->application->fresh()->registration_id);
        $this->assertSame(PreRegistration::STATUS_IN_CONFIRMATION, $this->application->preregistration->fresh()->status);
    }

    public function test_process_physical_deadlines_are_snapshotted_and_retry_notice_never_claims_final_enrollment(): void
    {
        DB::table('processes')->where('id', $this->application->preregistration->process_id)->update(['physical_delivery_days' => 12, 'physical_retry_days' => 4]);
        $this->approve();
        $deadline = $this->application->physical_deadline;
        $this->assertSame(now()->addDays(12)->endOfDay()->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        DB::table('processes')->where('id', $this->application->preregistration->process_id)->update(['physical_delivery_days' => 20]);
        $this->approve();
        $this->assertTrue($deadline->equalTo($this->application->physical_deadline));
        $this->post(route('bc-registration.physical.review', $this->application), [
            'subject' => 'document:' . $this->application->documents->first()->id, 'decision' => 'PENDING', 'reason' => 'Apresente original legível.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $retry = DB::table('bc_physical_reviews')->where('registration_request_id', $this->application->id)->first()->deadline;
        $this->assertSame(now()->addDays(4)->endOfDay()->format('Y-m-d H:i:s'), $retry);
        $mail = new GuardianRegistrationNotice('PHYSICAL_REQUIRED', $this->application->source_reference, $deadline->format('d/m/Y H:i'));
        $rendered = $mail->render();
        $this->assertStringContainsString($this->application->source_reference, $rendered);
        $this->assertStringContainsString('Matrícula em confirmação', $rendered);
        $this->assertStringNotContainsString('foi efetivada', $rendered);
    }

    public function test_existing_intermediate_registration_is_reused_and_native_cancellation_preserves_student(): void
    {
        $this->approve();
        $id = $this->application->intermediate_registration_id;
        $student = $this->application->student_id;
        LegacyRegistration::findOrFail($id)->update(['observacao' => 'Observação oficial preservada.']);
        $count = LegacyRegistration::count();
        $this->application->update(['intermediate_registration_id' => null, 'integration_status' => 'PENDING']);
        $this->approve();
        $this->assertSame($id, $this->application->intermediate_registration_id);
        $this->assertSame($count, LegacyRegistration::count());
        $this->assertSame('Observação oficial preservada.', LegacyRegistration::findOrFail($id)->observacao);
        $this->post(route('bc-registration.action', $this->application), ['action' => 'cancel', 'reason' => 'Cancelamento solicitado pelo responsável.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(0, LegacyRegistration::findOrFail($id)->ativo);
        $this->assertSame($student, $this->application->fresh()->student_id);
        $this->assertNull($this->application->fresh()->registration_id);
    }

    public function test_presential_documentary_delivery_still_requires_explicit_physical_confirmation(): void
    {
        $this->application->update(['attendance_mode' => AttendanceMode::InPerson]);
        $this->approve();
        $this->postJson(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertUnprocessable();
        $this->reviewAll();
        $this->post(route('bc-registration.action', $this->application), ['action' => 'finalize'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PreRegistration::STATUS_ACCEPTED, $this->application->preregistration->fresh()->status);
    }

    public function test_physical_review_rejects_foreign_document_and_requires_reason_and_current_deadline(): void
    {
        $this->approve();
        $url = route('bc-registration.physical.review', $this->application);
        $this->postJson($url, ['subject' => 'document:999999999', 'decision' => 'APPROVED'])->assertUnprocessable();
        $this->postJson($url, ['subject' => 'cadastro', 'decision' => 'PENDING'])->assertUnprocessable();
        $subject = 'document:' . $this->application->documents->first()->id;
        $this->application->update(['physical_deadline' => now()->subDay()]);
        $this->postJson($url, ['subject' => $subject, 'decision' => 'APPROVED'])->assertUnprocessable();
        $this->post($url, ['subject' => $subject, 'decision' => 'PENDING', 'reason' => 'Prazo de regularização concedido após atendimento.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, ['subject' => $subject, 'decision' => 'APPROVED'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs(LegacyUser::findOrFail(BcDemoEntity::where('key', 'user.operador.taquaras')->value('legacy_id')));
        $this->postJson($url, ['subject' => 'cadastro', 'decision' => 'APPROVED'])->assertForbidden();
    }
}

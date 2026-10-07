<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\GuardianProfiles;
use App\EnrollmentRequests\PhysicalConfirmation;
use App\EnrollmentRequests\PhysicalNotice;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\LegacyRegistration;
use App\Services\EnrollmentService;
use App\Services\RegistrationService;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\RejectPreRegistrations;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;

class NativePhysicalProtectionTest extends PhysicalConfirmationTest
{
    private function fixture(): array
    {
        return [
            (new ReflectionProperty(PhysicalConfirmationTest::class, 'application'))->getValue($this),
            (new ReflectionProperty(PhysicalConfirmationTest::class, 'actor'))->getValue($this),
        ];
    }

    private function integrated(): array
    {
        [$application, $actor] = $this->fixture();
        app(RegistrationWorkflow::class)->approve($application, $actor);
        $application->refresh();
        $this->assertSame('INTEGRATED', $application->integration_status);

        return [$application, $actor, LegacyRegistration::findOrFail($application->intermediate_registration_id)];
    }

    public function test_native_service_prevents_duplicate_intermediate_enrollment(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $class = $application->schoolClass;
        $this->assertSame(1, $registration->activeEnrollments()->count());
        $this->expectException(ValidationException::class);
        (new EnrollmentService($actor))->enroll($registration, $class, max(now()->startOfDay(), $class->begin_academic_year));
    }

    public function test_native_status_service_blocks_promotion_before_physical_review(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        foreach ([1, 2, 3, 7, 8, 10, 12, 13, 14] as $status) {
            try {
                (new RegistrationService($actor))->updateStatus($registration, ['nova_situacao' => $status]);
                $this->fail('Promoção nativa sem conferência física deveria ser bloqueada.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('physical', $error->errors());
                $this->assertSame(11, $registration->fresh()->aprovado);
            }
        }
    }

    public function test_legacy_status_update_cannot_bypass_physical_confirmation(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $legacy = new \clsPmieducarMatricula(cod_matricula: $registration->getKey(), aprovado: 3);
        $this->expectException(ValidationException::class);
        $legacy->edita();
    }

    public function test_legacy_enrollment_uses_native_guard_and_cannot_duplicate_reservation(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $legacy = new \clsPmieducarMatriculaTurma(ref_cod_matricula: $registration->getKey(),
            ref_cod_turma: $application->school_class_id, ref_usuario_cad: $actor->getKey());
        $legacy->data_enturmacao = now()->toDateString();
        $this->expectException(ValidationException::class);
        $legacy->cadastra();
    }

    public function test_original_rejection_closes_bc_and_cancels_intermediate_registration(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $vacancies = $application->schoolClass->vacancies;
        app(RejectPreRegistrations::class)(null, ['ids' => [$application->pmd_preregistration_id], 'justification' => 'Auditoria transacional.']);
        $this->assertSame(PreRegistration::STATUS_REJECTED, $application->preregistration->fresh()->status);
        $this->assertSame('INDEFERIDA', $application->fresh()->status->value);
        $this->assertSame(11, $registration->fresh()->aprovado);
        $this->assertEquals(0, $registration->fresh()->ativo);
        $this->assertFalse($registration->activeEnrollments()->exists());
        $this->assertSame($vacancies + 1, $application->schoolClass->fresh()->vacancies);
    }

    public function test_cadastral_correction_blocks_changes_after_physical_retry_deadline(): void
    {
        [$application, $actor] = $this->integrated();
        app(PhysicalConfirmation::class)->review($application, $actor, 'cadastro', false, 'Correção de identificação solicitada na auditoria.');
        DB::table('bc_physical_reviews')->where('registration_request_id', $application->id)->where('subject', 'cadastro')
            ->update(['deadline' => now()->subDay()]);
        $profile = app(GuardianProfiles::class)->claim($application->pmd_preregistration_id);
        $name = $application->preregistration->student->name . ' corrigido';
        $this->expectException(ValidationException::class);
        app(DeclaredStudentData::class)->update($profile, $application->pmd_preregistration_id, ['name' => $name], 'Correção após prazo na auditoria.');
    }

    public function test_intermediate_registration_has_shift_for_native_queue(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $shift = $application->schoolClass->turma_turno_id;
        $this->assertNotNull($shift);
        $this->assertEquals($shift, $registration->turno_pre_matricula);
        $this->assertTrue(LegacyRegistration::whereKey($registration->getKey())->where('aprovado', 11)
            ->where('turno_pre_matricula', $shift)->exists());
    }

    public function test_physical_deadline_monitor_is_idempotent_and_preserves_intermediate_registration(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $application->update(['physical_deadline' => now()->addHours(12)]);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $count = $application->events()->where('event', 'PHYSICAL_DEADLINE_REMINDER')->count();
        $this->assertGreaterThan(0, $count);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $this->assertSame($count, $application->events()->where('event', 'PHYSICAL_DEADLINE_REMINDER')->count());
        $application->update(['physical_deadline' => now()->subDay()]);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $expired = $application->events()->where('event', 'PHYSICAL_DEADLINE_EXPIRED')->count();
        $this->assertGreaterThan(0, $expired);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $this->assertSame($expired, $application->events()->where('event', 'PHYSICAL_DEADLINE_EXPIRED')->count());
        $this->assertSame('APROVADA', $application->fresh()->status->value);
        $this->assertEquals(1, $registration->fresh()->ativo);
        $this->assertSame(11, $registration->fresh()->aprovado);
    }

    public function test_pending_retry_uses_its_own_deadline_and_invalidates_old_notice(): void
    {
        [$application, $actor] = $this->integrated();
        $subject = 'document:'.$application->documents()->firstOrFail()->id;
        app(PhysicalConfirmation::class)->review($application, $actor, $subject, false, 'Apresente original legível.');
        $application->update(['physical_deadline' => now()->subDay()]);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $this->assertFalse($application->events()->where('event', 'PHYSICAL_DEADLINE_EXPIRED')->where('metadata->subject', $subject)->exists());
        DB::table('bc_physical_reviews')->where('registration_request_id', $application->id)->where('subject', $subject)
            ->update(['deadline' => now()->addHours(12)]);
        $this->artisan('bc:monitor-physical-deadlines')->assertSuccessful();
        $event = $application->events()->where('event', 'PHYSICAL_DEADLINE_REMINDER')->where('metadata->subject', $subject)->firstOrFail();
        $raw = DB::table('bc_registration_events')->find($event->id);
        $request = DB::table('bc_registration_requests')->find($application->id);
        $this->assertFalse(PhysicalNotice::obsolete('PHYSICAL_REMINDER', $request, $raw));
        app(PhysicalConfirmation::class)->review($application, $actor, $subject, false, 'Novo prazo motivado.');
        $this->assertTrue(PhysicalNotice::obsolete('PHYSICAL_REMINDER', $request, $raw));
    }

    public function test_physical_messages_use_physical_deadline_and_protocol(): void
    {
        [$application] = $this->integrated();
        Http::preventStrayRequests();
        Http::fake(['bridge.test/*' => Http::response([], 202)]);
        $application->preregistration->responsible->update(['mobile' => '(47) 99999-1234']);
        config(['bc-messages.enabled' => true, 'bc-messages.start_at' => now()->subSecond()->toDateTimeString(),
            'bc-messages.channels.sms' => ['enabled' => true, 'url' => 'https://bridge.test/sms', 'token' => null],
            'bc-messages.channels.whatsapp.enabled' => false]);
        $this->artisan('bc:send-guardian-messages')->assertSuccessful();
        Http::assertSent(fn ($message) => str_contains($message->data()['message'], 'Matrícula em confirmação')
            && str_contains($message->data()['message'], $application->source_reference)
            && str_contains($message->data()['message'], $application->physical_deadline->format('d/m/Y H:i')));
    }

    public function test_rejection_of_changed_native_registration_rolls_back_pmd_and_bc(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        $registration->update(['aprovado' => 3]);
        try {
            app(RejectPreRegistrations::class)(null, ['ids' => [$application->pmd_preregistration_id], 'justification' => 'Não deve alterar vínculo oficial.']);
            $this->fail('O indeferimento deveria ser bloqueado.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('registration', $error->errors());
        }
        $this->assertSame(PreRegistration::STATUS_IN_CONFIRMATION, $application->preregistration->fresh()->status);
        $this->assertSame('APROVADA', $application->fresh()->status->value);
        $this->assertEquals(1, $registration->fresh()->ativo);
    }

    public function test_operator_can_renew_expired_cadastral_retry_without_approving_incorrect_data(): void
    {
        [$application, $actor] = $this->integrated();
        app(PhysicalConfirmation::class)->review($application, $actor, 'cadastro', false, 'Corrija o nome.');
        DB::table('bc_physical_reviews')->where('registration_request_id', $application->id)->where('subject', 'cadastro')
            ->update(['deadline' => now()->subDay()]);
        app(PhysicalConfirmation::class)->review($application, $actor, 'cadastro', false, 'Novo prazo após atendimento na escola.');
        $this->assertSame('CORRECTION', app(DeclaredStudentData::class)->reviewFor($application->pmd_preregistration_id)->status);
        $profile = app(GuardianProfiles::class)->claim($application->pmd_preregistration_id);
        $name = $application->preregistration->student->name.' corrigido';
        app(DeclaredStudentData::class)->update($profile, $application->pmd_preregistration_id, ['name' => $name], 'Regularização no novo prazo.');
        $this->assertSame($name, $application->preregistration->fresh()->student->name);
        $this->assertNull($application->fresh()->approved_at);
    }

    public function test_backfill_changes_only_matching_bc_intermediate_registration(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        // Historical migration applies only to the V2 fixture without enrollment.
        $registration->activeEnrollments()->update(['ativo' => 0]);
        $registration->update(['turno_pre_matricula' => null]);
        $unlinked = $registration->replicate();
        $unlinked->saveOrFail();
        $migration = require database_path('migrations/2026_10_05_234000_backfill_bc_intermediate_registration_shift.php');
        $migration->up();
        $this->assertEquals($application->schoolClass->turma_turno_id, $registration->fresh()->turno_pre_matricula);
        $this->assertNull($unlinked->fresh()->turno_pre_matricula);
    }

    public function test_missing_intermediate_enrollment_blocks_promotion_without_consuming_another_vacancy(): void
    {
        [$application, $actor, $registration] = $this->integrated();
        foreach ($application->documents()->pluck('id')->map(fn ($id) => 'document:'.$id)->push('cadastro') as $subject) {
            app(PhysicalConfirmation::class)->review($application, $actor, $subject, true, null);
        }
        $registration->activeEnrollments()->update(['ativo' => 0]);
        try {
            app(PhysicalConfirmation::class)->confirm($application, $actor);
            $this->fail('A confirmação não deve criar uma nova enturmação.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('physical', $error->errors());
        }
        $this->assertNull($application->fresh()->physical_confirmed_at);
        $this->assertNull($application->fresh()->physical_confirmed_by);
        $this->assertNull($application->fresh()->registration_id);
        $this->assertSame(11, $registration->fresh()->aprovado);
        $this->assertFalse($registration->activeEnrollments()->exists());
    }
}

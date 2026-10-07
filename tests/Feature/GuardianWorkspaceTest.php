<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\GuardianCommunications;
use App\EnrollmentRequests\GuardianDependents;
use App\EnrollmentRequests\GuardianProfiles;
use App\EnrollmentRequests\PmdDocumentAudit;
use App\Mail\GuardianAccessCode;
use App\Models\BcDemoEntity;
use App\Models\LegacyStudent;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuardianWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    private PreRegistration $pmd;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Mail::fake();
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $this->pmd = PreRegistration::query()->whereIn('id', RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->select('pmd_preregistration_id'))->firstOrFail();
        $this->profileId = app(GuardianProfiles::class)->claim($this->pmd->id);
        $this->withSession(['bc_guardian_profile_id' => $this->profileId, 'bc_guardian_pmd_id' => (int) $this->pmd->id]);
    }

    private function dependent(): object
    {
        return DB::table('bc_guardian_dependents')->where('profile_id', $this->profileId)->firstOrFail();
    }

    private function notice(int $pmdId, string $kind = 'CORRECTION', bool $early = false): int
    {
        if ($early) {
            $event = app(PmdDocumentAudit::class)->record($pmdId, 'AUDIT_TEST_NOTICE');
            $origin = ['pmd_document_event_id' => $event];
        } else {
            $application = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->firstOrFail();
            $event = DB::table('bc_registration_events')->insertGetId([
                'registration_request_id' => $application->id, 'event' => 'DOCUMENT_REJECTED', 'actor_type' => 'TEST',
                'metadata' => json_encode(['reason' => 'Envie uma imagem legível do documento.']), 'created_at' => now(),
            ]);
            $origin = ['registration_event_id' => $event];
        }

        return DB::table('bc_guardian_notifications')->insertGetId($origin + [
            'kind' => $kind, 'created_at' => now(), 'updated_at' => now(), 'attempts' => 0,
        ]);
    }

    public function test_dependent_can_be_created_without_creating_native_student_or_application(): void
    {
        $students = LegacyStudent::count();
        $applications = PreRegistration::count();
        $this->get('/matricula-digital/dependentes/novo')->assertOk()->assertSee('Adicionar dependente')->assertSee('workflow.css');
        $this->post('/matricula-digital/dependentes', ['name' => 'Dependente independente', 'date_of_birth' => '2016-02-10'])
            ->assertRedirect('/matricula-digital/perfil')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bc_guardian_dependents', ['profile_id' => $this->profileId, 'name' => 'Dependente independente']);
        $this->assertSame($students, LegacyStudent::count());
        $this->assertSame($applications, PreRegistration::count());
        $this->get('/matricula-digital/perfil')->assertOk()->assertSee('Dependente independente');
    }

    public function test_profile_edit_does_not_overwrite_submitted_snapshot_and_keeps_audit(): void
    {
        $dependent = $this->dependent();
        $name = $this->pmd->student->name;
        $studentId = $this->pmd->student_id;
        $this->post('/matricula-digital/dependentes/'.$dependent->id, [
            'name' => 'Nome para o perfil', 'reason' => 'Revisão dos dados guardados.',
        ])->assertRedirect('/matricula-digital/perfil')->assertSessionHasNoErrors();
        $this->assertSame($name, $this->pmd->student->fresh()->name);
        $this->assertSame($studentId, $this->pmd->fresh()->student_id);
        $event = DB::table('bc_guardian_dependent_events')->where('dependent_id', $dependent->id)->where('event', 'DEPENDENT_UPDATED')->firstOrFail();
        $this->assertSame('Nome para o perfil', json_decode($event->changes, true)['name']['after']);
        $this->assertSame($dependent->id, app(GuardianDependents::class)->ensure($this->profileId, $this->pmd->id));
    }

    public function test_dependent_stays_linked_when_ficha_correction_clones_student_snapshot(): void
    {
        $dependent = $this->dependent();
        $original = $this->pmd->student_id;
        DB::table('preregistrations')->where('id', $this->pmd->id)->update(['status' => PreRegistration::STATUS_WAITING]);
        RegistrationRequest::query()->where('pmd_preregistration_id', $this->pmd->id)->update(['pmd_preregistration_id' => null]);
        app(DeclaredStudentData::class)->update($this->profileId, $this->pmd->id, ['name' => 'Nome corrigido na ficha'], 'Correção declarada.');
        $this->assertNotEquals($original, $this->pmd->fresh()->student_id);
        $this->assertSame($dependent->id, app(GuardianDependents::class)->ensure($this->profileId, $this->pmd->id));
        $this->assertSame(1, app(GuardianDependents::class)->all($this->profileId)->count());
        $this->get('/matricula-digital/perfil')->assertOk()->assertSee('Nome corrigido na ficha');
    }

    public function test_foreign_dependent_and_unverified_application_cannot_be_organized_or_viewed(): void
    {
        $foreign = DB::table('bc_guardian_profiles')->insertGetId(['email' => 'outro-workspace@invalid.test', 'name' => 'Outro perfil',
            'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $id = app(GuardianDependents::class)->save($foreign, ['name' => 'Dependente de outro perfil']);
        $this->get('/matricula-digital/dependentes/'.$id.'/editar')->assertForbidden();
        $this->post('/matricula-digital/dependentes/'.$id, ['name' => 'Não alterar', 'reason' => 'Tentativa'])->assertForbidden();
        $this->post('/matricula-digital/inscricoes/'.$this->pmd->id.'/dependente', ['dependent_id' => $id, 'reason' => 'Tentativa'])->assertForbidden();
        $other = PreRegistration::query()->whereKeyNot($this->pmd->id)->firstOrFail();
        $this->post('/matricula-digital/inscricoes/'.$other->id.'/dependente', ['dependent_id' => $this->dependent()->id, 'reason' => 'Tentativa'])->assertForbidden();
        $this->assertSame('Dependente de outro perfil', DB::table('bc_guardian_dependents')->find($id)->name);
    }

    public function test_organization_changes_only_profile_grouping_and_not_authorization_or_native_ids(): void
    {
        $id = app(GuardianDependents::class)->save($this->profileId, ['name' => 'Dependente escolhido']);
        $student = $this->pmd->student_id;
        $application = RegistrationRequest::query()->where('pmd_preregistration_id', $this->pmd->id)->firstOrFail();
        $native = $application->registration_id;
        $this->post('/matricula-digital/inscricoes/'.$this->pmd->id.'/dependente', ['dependent_id' => $id, 'reason' => 'Inscrição da mesma criança.'])
            ->assertRedirect('/matricula-digital/perfil')->assertSessionHasNoErrors();
        $this->assertSame($id, app(GuardianDependents::class)->ensure($this->profileId, $this->pmd->id));
        $this->assertSame($student, $this->pmd->fresh()->student_id);
        $this->assertSame($native, $application->fresh()->registration_id);
        $this->assertCount(1, app(GuardianProfiles::class)->applications($this->profileId));
    }

    public function test_communications_show_both_origins_only_for_verified_applications_and_read_is_idempotent(): void
    {
        $own = $this->notice($this->pmd->id);
        $early = $this->notice($this->pmd->id, 'PREREGISTRATION_REGISTERED', true);
        $other = PreRegistration::query()->whereKeyNot($this->pmd->id)
            ->whereIn('id', RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->select('pmd_preregistration_id'))->firstOrFail();
        $foreign = $this->notice($other->id);
        $this->get('/matricula-digital/comunicacoes')->assertOk()->assertSee($this->pmd->protocol)->assertDontSee($other->protocol)
            ->assertSee('Envie uma imagem legível do documento.')->assertSee('Pré-matrícula registrada');
        $this->post('/matricula-digital/comunicacoes/'.$foreign.'/lida')->assertForbidden();
        $before = app(GuardianCommunications::class)->unreadCount($this->profileId);
        $this->post('/matricula-digital/comunicacoes/'.$own.'/lida')->assertRedirect();
        $this->post('/matricula-digital/comunicacoes/'.$own.'/lida')->assertRedirect();
        $this->assertSame($before - 1, app(GuardianCommunications::class)->unreadCount($this->profileId));
        $this->assertSame(1, DB::table('bc_guardian_notice_reads')->where('profile_id', $this->profileId)->where('notification_id', $own)->count());
        $this->get('/matricula-digital/comunicacoes?unread=1')->assertOk()->assertDontSee('Envie uma imagem legível do documento.');
        $this->assertFalse(DB::table('bc_guardian_notice_reads')->where('notification_id', $early)->exists());
    }

    public function test_communication_filters_do_not_allow_foreign_applications_and_support_owned_dependents(): void
    {
        $this->notice($this->pmd->id);
        $other = PreRegistration::query()->whereKeyNot($this->pmd->id)->firstOrFail();
        $this->get('/matricula-digital/comunicacoes?pmd='.$other->id)->assertForbidden();
        $this->get('/matricula-digital/comunicacoes?dependent=999999999')->assertForbidden();
        $this->get('/matricula-digital/comunicacoes?dependent='.$this->dependent()->id)->assertOk()->assertSee($this->pmd->protocol);
    }

    public function test_anonymous_access_and_invalid_dependent_data_are_rejected(): void
    {
        $this->post('/matricula-digital/dependentes', ['name' => '', 'date_of_birth' => '2999-01-01'])->assertSessionHasErrors(['name', 'date_of_birth']);
        $this->post('/matricula-digital/sair')->assertRedirect();
        $this->get('/matricula-digital/dependentes/novo')->assertForbidden();
        $this->get('/matricula-digital/comunicacoes')->assertForbidden();
        $this->post('/matricula-digital/dependentes', ['name' => 'Não autorizado'])->assertForbidden();
    }

    public function test_same_declared_identity_does_not_link_an_unverified_application(): void
    {
        $other = PreRegistration::query()->whereKeyNot($this->pmd->id)->firstOrFail();
        DB::table('preregistrations')->where('id', $other->id)->update(['student_id' => $this->pmd->student_id]);
        $this->get('/matricula-digital/perfil')->assertOk()->assertDontSee($other->protocol);
        $this->assertCount(1, app(GuardianProfiles::class)->applications($this->profileId));
        $this->post('/matricula-digital/inscricoes/'.$other->id.'/dependente', [
            'dependent_id' => $this->dependent()->id, 'reason' => 'Mesmo estudante declarado.',
        ])->assertForbidden();
    }

    public function test_invalid_cpf_is_rejected_without_changing_the_dependent_or_application(): void
    {
        $dependent = $this->dependent();
        $originalCpf = $this->pmd->student->cpf;
        foreach (['11111111111', '000.000.000-00', '52998224724', 'abc52998224725'] as $cpf) {
            $this->post('/matricula-digital/dependentes/'.$dependent->id, [
                'name' => $dependent->name, 'cpf' => $cpf, 'reason' => 'Correção do CPF.',
            ])->assertSessionHasErrors('cpf');
            $this->post('/matricula-digital/ficha', [
                'student' => ['name' => $dependent->name, 'date_of_birth' => '2014-01-01', 'cpf' => $cpf],
                'reason' => 'Correção do CPF.',
            ])->assertSessionHasErrors('student.cpf');
        }
        $this->assertSame($dependent->cpf, $this->dependent()->cpf);
        $this->assertSame($originalCpf, $this->pmd->student->fresh()->cpf);
        $this->assertFalse(DB::table('bc_guardian_dependent_events')->where('dependent_id', $dependent->id)
            ->where('event', 'DEPENDENT_UPDATED')->exists());
    }

    public function test_dependent_accepts_valid_cpf_with_or_without_punctuation_and_optional_cpf(): void
    {
        foreach (['52998224725', '529.982.247-25', null] as $cpf) {
            $this->post('/matricula-digital/dependentes', ['name' => 'Dependente CPF', 'cpf' => $cpf])
                ->assertRedirect('/matricula-digital/perfil')->assertSessionHasNoErrors();
            $this->assertTrue(DB::table('bc_guardian_dependents')->where('profile_id', $this->profileId)
                ->where('name', 'Dependente CPF')->where('cpf', $cpf)->exists());
        }
    }

    public function test_profile_keeps_the_code_confirmation_visible_after_requesting_access(): void
    {
        $this->from('/matricula-digital/perfil')->post('/matricula-digital/acesso', [
            'protocol' => $this->pmd->protocol, 'email' => $this->pmd->responsible->email,
        ])->assertRedirect('/matricula-digital/perfil')->assertSessionHas('bc_guardian_challenge');
        Mail::assertSent(GuardianAccessCode::class);
        $this->get('/matricula-digital/perfil')->assertOk()
            ->assertSee('<details class="bc-school-summary"  open ', false)
            ->assertSee('Solicitar novo código')->assertSee('Confirmar vínculo');
    }

    public function test_communications_paginate_and_profile_reading_does_not_change_other_profiles(): void
    {
        for ($index = 0; $index < 21; $index++) {
            $this->notice($this->pmd->id, 'DOCUMENT_RECEIVED', true);
        }
        $page = app(GuardianCommunications::class)->page($this->profileId, null, null);
        $this->assertSame(20, $page->count());
        $this->assertGreaterThanOrEqual(21, $page->total());
        $this->get('/matricula-digital/comunicacoes?page=2')->assertOk()->assertSee('Página 2');
        $otherProfile = DB::table('bc_guardian_profiles')->insertGetId(['email' => 'outro-leitor@invalid.test', 'name' => 'Outro leitor',
            'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bc_guardian_profile_applications')->insert(['profile_id' => $otherProfile, 'pmd_id' => $this->pmd->id, 'verified_at' => now()]);
        $notice = $page->first()->id;
        $otherCount = app(GuardianCommunications::class)->unreadCount($otherProfile);
        app(GuardianCommunications::class)->markRead($this->profileId, $notice);
        $this->assertSame($otherCount, app(GuardianCommunications::class)->unreadCount($otherProfile));
    }
}

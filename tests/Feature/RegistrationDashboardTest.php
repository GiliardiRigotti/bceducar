<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDashboardTest extends TestCase
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

    public function test_guardian_document_session_does_not_grant_operator_access(): void
    {
        $request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $document = $request->documents()->firstOrFail();
        $before = $document->status;
        $this->withSession(['bc_guardian_pmd_id' => $request->pmd_preregistration_id]);
        $this->get(route('bc-registration.index'))->assertRedirect(route('login'));
        $this->get(route('bc-registration.show', $request))->assertRedirect(route('login'));
        $this->postJson(route('bc-registration.review', $document), ['status' => 'APROVADO'])->assertUnauthorized();
        $this->assertSame($before, $document->fresh()->status);
    }

    public function test_operator_has_explicit_document_approval_buttons_without_guardian_access_links(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $request->update(['attendance_mode' => AttendanceMode::Online, 'status' => RequestStatus::AwaitingDocuments, 'approved_at' => null]);
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => PreRegistration::STATUS_SUMMONED]);
        $document = $request->documents()->firstOrFail();
        $document->update(['status' => DocumentStatus::Sent, 'received_at' => $request->document_deadline->copy()->subHour()]);
        $this->actingAs($actor)->get(route('bc-registration.show', $request))->assertOk()
            ->assertSee('Aprovar documento')->assertSee('Solicitar correção')->assertSee('Rejeitar documento')
            ->assertDontSee('Para acessar como responsável')->assertDontSee('Acesso do responsável');
        $this->post(route('bc-registration.review', $document), ['status' => 'APROVADO'])->assertRedirect();
        $this->assertSame(DocumentStatus::Approved, $document->fresh()->status);
        $this->assertNull($request->fresh()->registration_id);
    }

    public function test_inactive_operator_cannot_open_documentary_triage(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($actor);
        $actor->update(['ativo' => 0]);
        $this->get(route('bc-registration.index'))->assertForbidden();
        $this->get(route('bc-registration.intake'))->assertForbidden();
    }

    public function test_individual_rejection_explains_reason_to_guardian_and_allows_replacement(): void
    {
        Mail::fake();
        Storage::fake('registration-documents');
        $request = $this->readyForFinalApproval();
        $request->update(['seed_source' => null]);
        $document = $request->documents()->firstOrFail();
        $document->update(['status' => DocumentStatus::Sent]);
        $untouched = $request->documents()->where('id', '!=', $document->id)->firstOrFail();
        $page = $this->get(route('bc-registration.show', $request))->assertOk();
        $this->assertGreaterThan(strpos($page->getContent(), 'Documentos e versões'), strpos($page->getContent(), 'Aprovação da documentação e matrícula'));
        $this->post(route('bc-registration.review', $document), [
            'status' => 'REJEITADO', 'reason_option' => 'ILLEGIBLE', 'reason' => 'A data de nascimento não está legível.',
        ])->assertRedirect();
        $document->refresh();
        $this->assertSame(DocumentStatus::Rejected, $document->status);
        $this->assertStringContainsString('Arquivo ilegível', $document->reason);
        $this->assertStringContainsString('A data de nascimento não está legível.', $document->reason);
        $this->assertSame(DocumentStatus::Approved, $untouched->fresh()->status);
        $this->assertDatabaseHas('bc_guardian_notifications', ['registration_event_id' => $request->events()->where('document_id', $document->id)->where('event', 'DOCUMENT_REJECTED')->value('id'), 'kind' => 'CORRECTION']);
        $this->withSession(['bc_guardian_pmd_id' => $request->pmd_preregistration_id]);
        $this->get('/matricula-digital')->assertOk()->assertSee($document->reason)->assertSee('Enviar novo documento');
        $this->post('/matricula-digital/documentos', [
            'document_type' => $document->document_type->value, 'replaces_id' => $document->id,
            'document' => UploadedFile::fake()->image('corrigido.png'),
        ])->assertRedirect();
        $this->assertSame(DocumentStatus::Replaced, $document->fresh()->status);
        $this->assertDatabaseHas('bc_registration_documents', ['registration_request_id' => $request->id, 'replaces_id' => $document->id, 'status' => 'ENVIADO']);
        $this->assertSame(DocumentStatus::Approved, $untouched->fresh()->status);
    }

    public function test_other_rejection_reason_requires_details_and_unknown_options_are_rejected(): void
    {
        $request = $this->readyForFinalApproval();
        $document = $request->documents()->firstOrFail();
        $document->update(['status' => DocumentStatus::Sent]);
        foreach (['OTHER', 'UNKNOWN'] as $option) {
            $this->postJson(route('bc-registration.review', $document), ['status' => 'REJEITADO', 'reason_option' => $option])->assertUnprocessable();
            $this->assertSame(DocumentStatus::Sent, $document->fresh()->status);
        }
    }

    public function test_final_approval_buttons_are_visible_and_registered_request_has_no_actions(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $request = RegistrationRequest::query()->where('status', 'AGUARDANDO_DOCUMENTOS')->firstOrFail();
        $this->actingAs($actor)->get(route('bc-registration.show', $request))->assertOk()
            ->assertSee('Primeiro aprove os documentos obrigatórios')
            ->assertSee('Aprovar documentação')->assertSee('Recusar documentação');
        $request->update(['status' => RequestStatus::Registered]);
        $this->get(route('bc-registration.show', $request))->assertOk()->assertDontSee('value="approve"', false);
    }

    public function test_approval_requires_explicit_finalization_and_repetition_is_idempotent(): void
    {
        $request = $this->readyForFinalApproval();
        $this->post(route('bc-registration.action', $request), ['action' => 'approve'])->assertRedirect();
        $request->refresh();
        $this->assertSame(RequestStatus::Approved, $request->status);
        $this->assertNull($request->registration_id);
        $this->post(route('bc-registration.action', $request), ['action' => 'finalize'])->assertRedirect();
        $request->refresh();
        $this->assertSame(RequestStatus::Registered, $request->status);
        $this->assertNotNull($request->registration_id);
        $this->assertDatabaseHas('pmieducar.matricula', ['cod_matricula' => $request->registration_id, 'ref_cod_aluno' => $request->student_id]);
        $this->assertDatabaseHas('pmieducar.matricula_turma', ['ref_cod_matricula' => $request->registration_id, 'ref_cod_turma' => $request->school_class_id, 'ativo' => 1]);
        $id = $request->registration_id;
        $this->post(route('bc-registration.action', $request), ['action' => 'approve'])->assertRedirect();
        $this->assertSame($id, $request->fresh()->registration_id);
    }

    public function test_full_class_blocks_finalization_and_preserves_document_approval(): void
    {
        $request = $this->readyForFinalApproval();
        $request->schoolClass->update(['max_aluno' => 0]);
        $this->post(route('bc-registration.action', $request), ['action' => 'approve'])->assertRedirect();
        $this->postJson(route('bc-registration.action', $request), ['action' => 'finalize'])->assertUnprocessable();
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->approved_at);
        $this->assertNull($request->fresh()->registration_id);
    }

    public function test_rejected_document_can_be_reentregued_after_total_deadline_in_both_modes(): void
    {
        Storage::fake('registration-documents');
        foreach (AttendanceMode::cases() as $mode) {
            $request = $this->readyForFinalApproval();
            $request->update(['attendance_mode' => $mode, 'document_deadline' => now()->subDay()]);
            $request->documents()->update(['received_at' => now()->subDays(2), 'delivery_deadline' => null]);
            DB::table('processes')->where('id', $request->preregistration->process_id)->update(['documentation_retry_days' => 2]);
            $document = $request->documents()->where('status', '!=', DocumentStatus::Replaced->value)->firstOrFail();
            $document->update(['status' => DocumentStatus::Sent]);
            $this->post(route('bc-registration.review', $document), ['status' => 'REJEITADO', 'reason_option' => 'ILLEGIBLE'])->assertRedirect();
            $document->refresh();
            $this->assertTrue($document->delivery_deadline->equalTo(now()->addDays(2)->endOfDay()->startOfSecond()));
            $this->assertFalse(app(RegistrationWorkflow::class)->expire($request->fresh()));
            $this->withSession(['bc_guardian_pmd_id' => $request->pmd_preregistration_id]);
            $this->get('/matricula-digital')->assertOk()->assertSee('Reentregue até');
            if ($mode === AttendanceMode::Online) {
                $this->post('/matricula-digital/documentos', ['document_type' => $document->document_type->value,
                    'replaces_id' => $document->id, 'document' => UploadedFile::fake()->image('corrigido.png')])->assertRedirect()->assertSessionHasNoErrors();
            } else {
                $this->post(route('bc-registration.receive-in-person', $request), ['types' => [$document->document_type->value]])->assertRedirect()->assertSessionHasNoErrors();
            }
            $replacement = $request->documents()->where('replaces_id', $document->id)->firstOrFail();
            $this->assertSame(DocumentStatus::Replaced, $document->fresh()->status);
            $this->assertTrue($replacement->delivery_deadline->equalTo($document->delivery_deadline));
            $this->post(route('bc-registration.review', $replacement), ['status' => 'APROVADO'])->assertRedirect();
            $this->assertSame(DocumentStatus::Approved, $replacement->fresh()->status);
        }
        $this->post(route('bc-registration.action', $request), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertNull($request->fresh()->registration_id);
    }

    public function test_reentrega_after_its_own_deadline_is_blocked_and_expires(): void
    {
        Storage::fake('registration-documents');
        $request = $this->readyForFinalApproval();
        $request->update(['document_deadline' => now()->subDays(3), 'status' => RequestStatus::Pending]);
        $document = $request->documents()->firstOrFail();
        $document->update(['status' => DocumentStatus::Rejected, 'received_at' => now()->subDays(4),
            'delivery_deadline' => now()->subHour(), 'reason' => 'Ilegível']);
        $this->withSession(['bc_guardian_pmd_id' => $request->pmd_preregistration_id]);
        $this->postJson('/matricula-digital/documentos', ['document_type' => $document->document_type->value,
            'replaces_id' => $document->id, 'document' => UploadedFile::fake()->image('tardio.png')])->assertUnprocessable();
        $this->assertSame(DocumentStatus::Rejected, $document->fresh()->status);
        $this->assertTrue(app(RegistrationWorkflow::class)->expire($request->fresh()));
    }

    private function readyForFinalApproval(): RegistrationRequest
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($actor);
        $request = RegistrationRequest::query()->where('protocol', 'BC-2026-0008')->firstOrFail();
        $request->update(['status' => RequestStatus::AwaitingReview, 'attendance_mode' => AttendanceMode::Online, 'approved_at' => null, 'registration_id' => null, 'document_deadline' => now()->addDays(7)]);
        $request->documents()->where('status', '!=', DocumentStatus::Replaced->value)->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => PreRegistration::STATUS_IN_CONFIRMATION]);
        $request->schoolClass->update(['max_aluno' => 100]);

        return $request->fresh();
    }

    public function test_pre_registration_uses_original_module_without_duplicate_intake(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()
            ->where('key', 'user.operador.medici')->value('legacy_id'));
        $this->actingAs($actor)->get(route('bc-registration.intake'))
            ->assertRedirect('/pre-matricula-digital/inscricoes');
        $this->get(route('bc-registration.index'))->assertOk()
            ->assertSee('href="/pre-matricula-digital/inscricoes"', false)
            ->assertDontSee('Deferir pré-matrícula');
        $this->post('/bc/matriculas/pmd/1', ['school_class_id' => 1])->assertStatus(405);
    }

    public function test_queue_progress_excludes_optional_replaced_and_late_documents_in_both_modes(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()
            ->where('key', 'user.operador.medici')->value('legacy_id'));
        $own = RegistrationRequest::query()->visibleTo($actor)->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $own->update(['document_deadline' => now()->addDay()]);
        $own->documents()->update(['status' => DocumentStatus::Pending, 'received_at' => null]);
        $required = $own->documents()->where('required', true)->get();
        $required[0]->update(['status' => DocumentStatus::Sent, 'received_at' => now()]);
        $required[1]->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $required[2]->update(['status' => DocumentStatus::Correction, 'received_at' => now()->addDays(2)]);
        $own->documents()->where('required', false)->update(['status' => DocumentStatus::Approved, 'received_at' => now()]);
        $previous = $required[0]->replicate();
        $previous->status = DocumentStatus::Replaced;
        $previous->save();
        DB::table('preregistrations')->where('id', $own->pmd_preregistration_id)->update(['created_at' => '2026-09-20 09:30:00']);
        $this->actingAs($actor);
        foreach (AttendanceMode::cases() as $mode) {
            $own->update(['attendance_mode' => $mode]);
            $response = $this->get(route('bc-registration.index', ['attendance_mode' => $mode->value]))
                ->assertOk()->assertSee('Documentos obrigatórios')->assertSee('20/09/2026 09:30');
            $row = $response->viewData('requests')->firstWhere('id', $own->id);
            $this->assertNotNull($row);
            $this->assertSame($required->count(), (int) $row->required_total);
            $this->assertSame(2, (int) $row->required_received);
            $this->assertSame(1, (int) $row->required_approved);
            $this->assertSame(1, (int) $row->required_corrections);
        }
        $own->update(['pmd_preregistration_id' => null, 'created_at' => '2026-09-21 10:45:00']);
        $this->get(route('bc-registration.index'))->assertOk()->assertSee('21/09/2026 10:45');
    }

    public function test_process_grade_and_period_filters_keep_school_scope_and_update_summary(): void
    {
        $actorId = BcDemoEntity::query()->where('key', 'user.operador.medici')->value('legacy_id');
        $actor = LegacyUser::query()->findOrFail($actorId);
        $own = RegistrationRequest::query()->visibleTo($actor)->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $other = RegistrationRequest::query()->where('school_id', '!=', $own->school_id)->firstOrFail();
        $processId = DB::table('preregistrations')->where('id', $own->pmd_preregistration_id)->value('process_id');
        $periodId = $own->schoolClass->turma_turno_id;
        $this->actingAs($actor);

        $response = $this->get(route('bc-registration.index', [
            'process_id' => $processId, 'grade_id' => $own->grade_id, 'period_id' => $periodId,
        ]))->assertOk()->assertSee($own->protocol)->assertDontSee($other->protocol);
        $requests = $response->viewData('requests');
        $this->assertGreaterThan(0, $requests->total());
        foreach ($requests as $item) {
            $this->assertSame($own->school_id, $item->school_id);
            $this->assertSame($own->grade_id, $item->grade_id);
            $this->assertSame($periodId, $item->schoolClass->turma_turno_id);
        }
        $this->assertSame($requests->total(), $response->viewData('counts')->sum());
        $this->assertSame($requests->total(), $response->viewData('schoolCounts')->sum());
        $this->assertSame(1, $response->viewData('schools')->count());

        $this->get(route('bc-registration.index', ['school_id' => $other->school_id]))
            ->assertOk()->assertDontSee($other->protocol)
            ->assertViewHas('requests', fn ($page) => $page->total() === 0);
    }

    public function test_csv_export_matches_filtered_dashboard_and_protects_school_scope(): void
    {
        $this->get(route('bc-registration.export'))->assertRedirect();
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()
            ->where('key', 'user.operador.medici')->value('legacy_id'));
        $own = RegistrationRequest::query()->visibleTo($actor)->firstOrFail();
        $other = RegistrationRequest::query()->where('school_id', '!=', $own->school_id)->firstOrFail();
        $this->actingAs($actor);
        $filters = ['grade_id' => $own->grade_id, 'period_id' => $own->schoolClass->turma_turno_id];
        $expected = $this->get(route('bc-registration.index', $filters))->assertOk()->viewData('requests')->total();
        $response = $this->get(route('bc-registration.export', $filters))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $this->assertSame(['Escola', 'Série', 'Turno', 'Ano letivo', 'Atendimento', 'Situação', 'Total'], fgetcsv($stream, null, ';', '"', ''));
        $total = 0;
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $this->assertSame($own->school->name, $row[0]);
            $this->assertSame($own->grade->name, $row[1]);
            $total += (int) $row[6];
        }
        fclose($stream);
        $this->assertSame($expected, $total);
        $this->assertStringNotContainsString($own->student->person->nome, $csv);
        $empty = $this->get(route('bc-registration.export', ['school_id' => $other->school_id]))->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($empty, "\n"));
    }

    public function test_csv_export_escapes_catalog_formulas_and_rejects_invalid_filters(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()
            ->where('key', 'user.operador.medici')->value('legacy_id'));
        $own = RegistrationRequest::query()->visibleTo($actor)->firstOrFail();
        $own->grade->update(['nm_serie' => '=1+1', 'descricao' => null]);
        $this->actingAs($actor);
        $csv = $this->get(route('bc-registration.export', ['grade_id' => $own->grade_id]))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->getJson(route('bc-registration.export', ['grade_id' => 'invalido']))->assertUnprocessable();
    }
}

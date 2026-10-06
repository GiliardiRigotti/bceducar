<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AcceptPmdRegistrations;
use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\DocumentType;
use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Models\BcDemoEntity;
use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use App\Services\EnrollmentService;
use App\Setting;
use Carbon\Carbon;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\Models\Classroom as PmdClassroom;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Services\EnrollmentService as PmdEnrollmentService;
use iEducar\Packages\PreMatricula\Services\RegistrationTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BalnearioCamboriuDemoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName(), 'Estes testes exigem banco isolado testing.');
        Carbon::setTestNow(Carbon::parse('2026-10-03 12:00:00', 'America/Sao_Paulo'));
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        // Historical demo links represent already deferred preregistrations.
        DB::table('preregistrations')->where('status', PreRegistration::STATUS_WAITING)
            ->whereIn('id', RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
                ->whereNotNull('pmd_preregistration_id')->select('pmd_preregistration_id'))
            ->update(['status' => PreRegistration::STATUS_SUMMONED]);

    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $login): LegacyUser
    {
        return LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->where('key', 'user.' . $login)->value('legacy_id'));
    }

    private function request(int $number): RegistrationRequest
    {
        return RegistrationRequest::query()->where('protocol', sprintf('BC-2026-%04d', $number))->firstOrFail();
    }

    public function test_seed_creates_native_entities_and_is_idempotent(): void
    {
        $counts = [];
        foreach (['bc_demo_entities', 'bc_registration_requests', 'bc_registration_documents', 'bc_registration_events', 'pmieducar.aluno', 'pmieducar.matricula', 'preregistrations'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->seed(BalnearioCamboriuDemoSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame(200, RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)->count());
        $this->assertSame(200, RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->count());
        $this->assertSame(15, RegistrationRequest::query()->where('status', RequestStatus::Registered->value)->count());
        $this->assertSame(100, BcDemoEntity::query()->where('key', 'like', 'guardian.%')->count());
        $this->assertSame(150, BcDemoEntity::query()->where('key', 'like', 'student.%')->count());
        $this->assertSame(15, BcDemoEntity::query()->where('key', 'like', 'school.%')->count());
    }

    public function test_production_is_rejected_before_any_write(): void
    {
        $count = RegistrationRequest::query()->count();
        $previous = app()->environment();
        app()->instance('env', 'production');
        try {
            (new BalnearioCamboriuDemoSeeder)->run();
            $this->fail('Seed de produção deve ser bloqueado.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('produção', $exception->getMessage());
            $this->assertSame($count, RegistrationRequest::query()->count());
        } finally {
            app()->instance('env', $previous);
        }
    }

    public function test_operator_scope_is_enforced_on_list_detail_documents_and_writes(): void
    {
        $operator = $this->user('operador.medici');
        $own = RegistrationRequest::query()->visibleTo($operator)->firstOrFail();
        $other = RegistrationRequest::query()->where('school_id', '!=', $own->school_id)->firstOrFail();
        $this->actingAs($operator)->get(route('bc-registration.show', $own))->assertOk();
        $this->get(route('bc-registration.show', $other))->assertForbidden();
        $this->post(route('bc-registration.action', $other), ['action' => 'approve'])->assertForbidden();
        $document = $other->documents()->whereNotNull('path')->first();
        if (!$document) {
            $document = RegistrationRequest::query()->where('school_id', '!=', $own->school_id)
                ->whereHas('documents', fn ($q) => $q->whereNotNull('path'))->firstOrFail()->documents()->whereNotNull('path')->firstOrFail();
        }
        $this->get(route('bc-registration.download', $document))->assertForbidden();
        $this->post(route('bc-registration.review', $document), ['status' => 'APROVADO'])->assertForbidden();
        $this->assertFalse(RegistrationRequest::query()->visibleTo($operator)->whereKey($other->id)->exists());
        $this->assertSame(200, RegistrationRequest::query()->visibleTo($this->user('admin.seduc'))->count());
        $this->assertSame(2, RegistrationRequest::query()->visibleTo($this->user('operador.duasunidades'))->distinct()->count('school_id'));
    }

    public function test_mandatory_documents_cannot_be_skipped(): void
    {
        $this->expectException(ValidationException::class);
        app(RegistrationWorkflow::class)->approve($this->request(2), $this->user('admin.seduc'));
    }

    public function test_document_replacement_keeps_previous_version_and_audit(): void
    {
        $request = $this->request(6);
        $document = $request->documents()->where('status', DocumentStatus::Correction->value)->firstOrFail();
        $workflow = app(RegistrationWorkflow::class);
        $replacement = $workflow->receive($request, $this->user('admin.seduc'), $document->document_type, $document->path, $document);
        $workflow->review($replacement, $this->user('admin.seduc'), DocumentStatus::Approved);
        $this->assertSame(DocumentStatus::Replaced, $document->fresh()->status);
        $this->assertSame($document->id, $replacement->replaces_id);
        $this->assertTrue($request->events()->where('event', 'DOCUMENT_REPLACED')->exists());
        $this->assertTrue(Storage::disk('registration-documents')->exists($document->path));
    }

    public function test_finalization_creates_native_registration_once_and_syncs_pmd(): void
    {
        $request = $this->request(8);
        $workflow = app(RegistrationWorkflow::class);
        $before = LegacyRegistration::query()->count();
        $registration = $workflow->finalize($request, $this->user('admin.seduc'));
        $again = $workflow->finalize($request, $this->user('admin.seduc'));
        $this->assertSame($registration->getKey(), $again->getKey());
        $this->assertSame($before + 1, LegacyRegistration::query()->count());
        $this->assertSame(1, LegacyEnrollment::query()->where('ref_cod_matricula', $registration->getKey())->count());
        $this->assertSame(RequestStatus::Registered, $request->fresh()->status);
        $this->assertSame(2, PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->status);
    }

    public function test_full_class_is_rejected_without_creating_registration(): void
    {
        $request = $this->request(38);
        $before = LegacyRegistration::query()->count();
        try {
            app(RegistrationWorkflow::class)->finalize($request, $this->user('admin.seduc'));
            $this->fail('Turma lotada deve impedir efetivação.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('lotada', $exception->getMessage());
            $this->assertSame($before, LegacyRegistration::query()->count());
        }
    }

    public function test_both_channels_keep_deadline_and_expire(): void
    {
        foreach (AttendanceMode::cases() as $mode) {
            $request = $this->request(2)->replicate(['protocol', 'pmd_preregistration_id']);
            $request->protocol = 'DEADLINE-' . $mode->value;
            $request->attendance_mode = $mode;
            $request->document_deadline = now()->subDay();
            $request->save();
            $workflow = app(RegistrationWorkflow::class);
            try {
                $workflow->receive($request, $this->user('admin.seduc'), DocumentType::BirthCertificate, 'mock.pdf');
                $this->fail('Recebimento após prazo deve ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('prazo', $exception->getMessage());
            }
            $this->assertTrue($workflow->expire($request));
            $this->assertFalse($workflow->expire($request));
            $this->assertSame($mode === AttendanceMode::InPerson ? RequestStatus::NoShow : RequestStatus::Expired, $request->fresh()->status);
            $this->assertSame(1, $request->events()->where('event', 'DEADLINE_EXPIRED')->count());
        }
    }

    public function test_pmd_deferment_releases_documents_without_finalizing_enrollment(): void
    {
        $request = $this->request(2);
        $this->actingAs($this->user('admin.seduc'));
        $mutation = app(AcceptPreRegistrations::class);
        $this->assertInstanceOf(AcceptPmdRegistrations::class, $mutation);
        $before = DB::table('pmieducar.matricula')->count();
        $result = $mutation(null, ['ids' => [$request->pmd_preregistration_id], 'classroom' => $request->school_class_id]);
        $this->assertCount(1, $result);
        $this->assertSame(PreRegistration::STATUS_SUMMONED, $result[0]->status);
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
        $this->assertNull($request->fresh()->registration_id);
    }

    public function test_pmd_queries_and_login_preserve_operator_scope(): void
    {
        $operator = $this->user('operador.medici');
        $this->actingAs($operator);
        $response = $this->getJson('/auth/login')->assertOk();
        $this->assertSame($operator->person->nome, $response->json('name'));
        $this->assertSame($operator->getKey(), auth()->id());
        $schoolIds = $operator->schools()->pluck('cod_escola')->all();
        $query = PreRegistration::query();
        $this->assertGreaterThan(0, (clone $query)->count());
        $this->assertSame(0, (clone $query)->whereNotIn('preregistrations.school_id', $schoolIds)->count());
        $token = Setting::query()->where('key', 'prematricula.token')->firstOrFail()->value;
        $graphql = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/graphql', [
            'query' => '{ preregistrations(first: 200) { data { id school { id } } } }',
        ])->assertOk();
        $this->assertNull($graphql->json('errors'), json_encode($graphql->json('errors')));
        $items = $graphql->json('data.preregistrations.data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertContains((int) $item['school']['id'], $schoolIds);
        }
    }

    public function test_demo_operator_can_authenticate_using_native_login(): void
    {
        $this->post('/login', ['login' => 'op.medici', 'password' => config('bc-demo.password')])->assertRedirect();
        $this->assertAuthenticatedAs($this->user('operador.medici'));
        $this->get(route('bc-registration.index'))->assertOk();
        $this->get(route('bc-registration.intake'))->assertRedirect('/pre-matricula-digital/inscricoes');
    }

    public function test_pmd_intake_is_idempotent_and_reuses_legacy_people(): void
    {
        $request = $this->request(2);
        $pmdId = $request->pmd_preregistration_id;
        DB::table('preregistrations')->where('id', $pmdId)->update([
            'document_submission_method' => $request->attendance_mode === AttendanceMode::InPerson ? 'IN_PERSON' : 'ONLINE',
        ]);
        $request->update(['pmd_preregistration_id' => null]);
        $before = DB::table('pmieducar.aluno')->count();
        $intake = app(PmdIntake::class);
        $imported = $intake->import($pmdId, $request->school_class_id, $this->user('admin.seduc'));
        $again = $intake->import($pmdId, $request->school_class_id, $this->user('admin.seduc'));
        $this->assertSame($request->student_id, $imported->student_id);
        $this->assertSame($request->guardian_id, $imported->guardian_id);
        $this->assertSame($imported->id, $again->id);
        $this->assertSame($before, DB::table('pmieducar.aluno')->count());
        $this->assertSame($request->attendance_mode, $imported->attendance_mode);
        $this->assertSame(10, $imported->documents()->count());
    }

    public function test_in_person_partial_delivery_is_recorded_without_upload_and_keeps_pending_documents(): void
    {
        $request = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->where('attendance_mode', AttendanceMode::InPerson->value)
            ->whereHas('documents', fn ($query) => $query->where('required', true)
                ->where('status', DocumentStatus::Pending->value), '>=', 2)->firstOrFail();
        $request->update(['status' => RequestStatus::AwaitingDocuments, 'document_deadline' => now()->addDay()]);
        $actor = $this->user('admin.seduc');
        $type = $request->documents()->where('required', true)->where('status', DocumentStatus::Pending->value)
            ->firstOrFail()->document_type;
        app(RegistrationWorkflow::class)->receiveInPerson($request, $actor, [$type], 'Entrega parcial na escola');
        $received = $request->documents()->where('document_type', $type->value)->firstOrFail();
        $this->assertSame(DocumentStatus::Sent, $received->status);
        $this->assertNull($received->path);
        $this->assertNotNull($received->received_at);
        $this->assertDatabaseHas('bc_registration_events', [
            'registration_request_id' => $request->id, 'document_id' => $received->id,
            'event' => EventType::Received->value, 'actor_id' => $actor->getKey(),
        ]);
        $this->assertGreaterThan(0, $request->documents()->where('required', true)
            ->where('status', DocumentStatus::Pending->value)->count());
        app(RegistrationWorkflow::class)->review($received, $actor, DocumentStatus::Approved);
        $this->expectException(ValidationException::class);
        app(RegistrationWorkflow::class)->approve($request, $actor);
    }

    public function test_document_progress_preserves_partial_delivery_and_required_corrections_without_optional_block(): void
    {
        $request = $this->request(2);
        $actor = $this->user('admin.seduc');
        $this->actingAs($actor);
        $request->update(['attendance_mode' => AttendanceMode::Online, 'status' => RequestStatus::AwaitingDocuments,
            'document_deadline' => now()->addDay()]);
        $deadline = $request->document_deadline->toDateTimeString();
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => PreRegistration::STATUS_SUMMONED]);
        $request->documents()->update(['status' => DocumentStatus::Pending, 'received_at' => null]);
        $workflow = app(RegistrationWorkflow::class);
        $required = $request->documents()->where('required', true)->get();
        $first = $workflow->receive($request, $actor, $required[0]->document_type, 'first.pdf');
        $this->assertSame(RequestStatus::AwaitingDocuments, $request->fresh()->status);
        $this->assertSame('AWAITING_DOCUMENTS', PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->documentation_status);
        foreach ($required->slice(1) as $document) {
            $workflow->receive($request, $actor, $document->document_type, 'next.pdf');
        }
        $this->assertSame(RequestStatus::AwaitingReview, $request->fresh()->status);
        $workflow->review($first, $actor, DocumentStatus::Correction, 'Imagem incompleta');
        $workflow->review($required[1]->fresh(), $actor, DocumentStatus::Approved);
        $this->assertSame(RequestStatus::Pending, $request->fresh()->status);
        $this->assertSame('CORRECTION_REQUIRED', PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->documentation_status);
        $replacement = $workflow->receive($request, $actor, $first->document_type, 'fixed.pdf', $first);
        $this->assertSame(DocumentStatus::Replaced, $first->fresh()->status);
        $this->assertSame($first->id, $replacement->replaces_id);
        foreach ($request->documents()->where('required', true)->where('status', DocumentStatus::Sent->value)->get() as $document) {
            $workflow->review($document, $actor, DocumentStatus::Approved);
        }
        $optional = $request->documents()->where('required', false)->firstOrFail();
        $optional = $workflow->receive($request, $actor, $optional->document_type, 'optional.pdf');
        $workflow->review($optional, $actor, DocumentStatus::Correction, 'Reenvio opcional');
        $this->assertSame(RequestStatus::AwaitingReview, $request->fresh()->status);
        $this->assertSame(DocumentStatus::Correction, $optional->fresh()->status);
        $workflow->approve($request, $actor);
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertSame($deadline, $request->fresh()->document_deadline->toDateTimeString());
    }

    public function test_official_pmd_enrollment_service_reuses_linked_student(): void
    {
        $request = $this->request(8);
        $pmd = PreRegistration::query()->findOrFail($request->pmd_preregistration_id);
        $pmd->external_person_id = $request->student->ref_idpes;
        $pmd->saveOrFail();
        $classroom = PmdClassroom::query()->findOrFail($request->school_class_id);
        $actor = $this->user('admin.seduc');
        $before = LegacyRegistration::query()->count();
        $service = new PmdEnrollmentService(new RegistrationTransferService, new EnrollmentService($actor));
        $registration = $service->enroll($pmd, $classroom);
        $this->assertSame($request->student_id, $registration->ref_cod_aluno);
        $this->assertSame($before + 1, LegacyRegistration::query()->count());
        $this->assertSame(1, LegacyEnrollment::query()->where('ref_cod_matricula', $registration->getKey())->count());
    }

    public function test_acceptance_updates_pmd_timeline_and_rejects_open_competing_application(): void
    {
        $approved = $this->request(8);
        $other = $this->request(9);
        $firstPmd = PreRegistration::query()->findOrFail($approved->pmd_preregistration_id);
        $otherPmd = PreRegistration::query()->findOrFail($other->pmd_preregistration_id);
        $this->assertSame($firstPmd->process_id, $otherPmd->process_id);
        $firstPmd->student->update(['cpf' => '99999999999']);
        $otherPmd->student->update(['cpf' => '99999999999']);
        $firstPmd->process->update(['reject_type_id' => 1]);
        app(RegistrationWorkflow::class)->finalize($approved, $this->user('admin.seduc'));
        $this->assertSame(PreRegistration::STATUS_REJECTED, $otherPmd->fresh()->status);
        $this->assertSame(RequestStatus::Rejected, $other->fresh()->status);
        $this->assertTrue($other->events()->where('event', EventType::Rejected->value)->exists());
        $this->assertDatabaseHas('timelines', [
            'model_type' => PreRegistration::class, 'model_id' => $firstPmd->id,
            'type' => 'preregistration-status-updated',
        ]);
        $this->expectException(ValidationException::class);
        app(RegistrationWorkflow::class)->approve($other, $this->user('admin.seduc'));
    }
}

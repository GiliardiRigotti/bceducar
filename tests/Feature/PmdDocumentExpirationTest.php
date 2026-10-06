<?php

namespace Tests\Feature;

use App\EnrollmentRequests\PmdDocumentExpiration;
use App\EnrollmentRequests\PmdIntake;
use App\EnrollmentRequests\RequestStatus;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PmdDocumentExpirationTest extends TestCase
{
    use DatabaseTransactions;

    private int $pmdId;

    private int $typeId;

    private RegistrationRequest $sourceRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $this->sourceRequest = $request;
        $this->pmdId = $request->pmd_preregistration_id;
        $request->update(['pmd_preregistration_id' => null]);
        $pmd = DB::table('preregistrations')->where('id', $this->pmdId)->first();
        DB::table('preregistrations')->where('id', $this->pmdId)->update([
            'status' => PreRegistration::STATUS_SUMMONED, 'document_submission_method' => 'ONLINE',
            'documentation_status' => 'AWAITING_DOCUMENTS', 'documentation_deadline' => now()->subDay(),
        ]);
        DB::table('preregistration_documents')->where('preregistration_id', $this->pmdId)->delete();
        DB::table('process_document_types')->where('process_id', $pmd->process_id)->delete();
        $this->typeId = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Nascimento', 'code' => 'CERTIDAO_NASCIMENTO', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert(['process_id' => $pmd->process_id,
            'document_type_id' => $this->typeId, 'required' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function update(array $values): void
    {
        DB::table('preregistrations')->where('id', $this->pmdId)->update($values);
    }

    private function document(string $status = 'PENDING', bool $late = false): void
    {
        DB::table('preregistration_documents')->insert([
            'preregistration_id' => $this->pmdId, 'document_type_id' => $this->typeId,
            'file_path' => 'testing/ficticio.png', 'original_filename' => 'ficticio.png',
            'mime_type' => 'image/png', 'file_size' => 10, 'hash' => str_repeat('a', 64),
            'status' => $status, 'created_at' => $late ? now() : now()->subDays(2), 'updated_at' => now(),
        ]);
    }

    public function test_command_expires_once_without_rejecting_or_creating_enrollment(): void
    {
        $before = DB::table('pmieducar.matricula')->count();
        $this->artisan('bc:expire-registration-requests')->assertSuccessful();
        $this->artisan('bc:expire-registration-requests')->assertSuccessful();
        $this->assertDatabaseHas('preregistrations', ['id' => $this->pmdId,
            'status' => PreRegistration::STATUS_SUMMONED, 'documentation_status' => 'DEADLINE_EXPIRED']);
        $this->assertSame(1, DB::table('preregistration_document_events')->where('preregistration_id', $this->pmdId)
            ->where('event', 'DOCUMENT_DEADLINE_EXPIRED')->count());
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId])
            ->get('/matricula-digital')->assertOk()->assertSee('O prazo de envio terminou')
            ->assertDontSee('Salvar forma de entrega')->assertDontSee('name="document"', false);
        $this->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertUnprocessable();
    }

    public function test_complete_on_time_delivery_remains_available_for_review(): void
    {
        $this->document();
        $this->update(['documentation_status' => 'AWAITING_REVIEW']);
        $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
        $this->assertDatabaseHas('preregistrations', ['id' => $this->pmdId, 'documentation_status' => 'AWAITING_REVIEW']);
        // A newer rejected version must not be hidden by an older timely delivery.
        $this->document('REJECTED');
        $this->assertTrue(app(PmdDocumentExpiration::class)->expire($this->pmdId));
    }

    public function test_late_delivery_does_not_prevent_expiration(): void
    {
        $this->document('PENDING', true);
        $this->assertTrue(app(PmdDocumentExpiration::class)->expire($this->pmdId));
    }

    public function test_waiting_legacy_finalized_and_future_registrations_are_untouched(): void
    {
        foreach ([PreRegistration::STATUS_WAITING, PreRegistration::STATUS_REJECTED, PreRegistration::STATUS_ACCEPTED] as $status) {
            $this->update(['status' => $status]);
            $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
        }
        $this->update(['status' => PreRegistration::STATUS_SUMMONED, 'documentation_status' => null]);
        $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
        $this->update(['documentation_status' => 'APPROVED']);
        $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
        $this->update(['documentation_status' => 'AWAITING_DOCUMENTS', 'documentation_deadline' => now()->addDay()]);
        $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
    }

    public function test_linked_request_is_left_to_bc_workflow(): void
    {
        RegistrationRequest::query()->whereNull('pmd_preregistration_id')->firstOrFail()
            ->update(['pmd_preregistration_id' => $this->pmdId]);
        $this->assertFalse(app(PmdDocumentExpiration::class)->expire($this->pmdId));
    }

    public function test_process_deadline_fallback_and_presential_no_show_are_audited(): void
    {
        $pmd = DB::table('preregistrations')->where('id', $this->pmdId)->first();
        DB::table('processes')->where('id', $pmd->process_id)->update(['documentation_deadline' => now()->subDay()]);
        $this->update(['documentation_deadline' => null, 'document_submission_method' => 'IN_PERSON',
            'status' => PreRegistration::STATUS_IN_CONFIRMATION]);
        $this->artisan('bc:expire-registration-requests')->assertSuccessful();
        $event = DB::table('preregistration_document_events')->where('preregistration_id', $this->pmdId)
            ->where('event', 'DOCUMENT_DEADLINE_EXPIRED')->first();
        $this->assertNotNull($event);
        $this->assertSame('SYSTEM', $event->actor_type);
        $this->assertTrue(json_decode($event->metadata, true)['no_show']);
        $this->assertDatabaseHas('preregistrations', ['id' => $this->pmdId, 'status' => PreRegistration::STATUS_IN_CONFIRMATION]);
    }

    public function test_timely_complete_delivery_can_be_imported_after_deadline_without_reopening_uploads(): void
    {
        $this->document();
        $this->update(['documentation_status' => 'AWAITING_REVIEW']);
        $actor = LegacyUser::query()->findOrFail($this->sourceRequest->created_by);
        $imported = app(PmdIntake::class)->import($this->pmdId, $this->sourceRequest->school_class_id, $actor);
        $this->assertSame(RequestStatus::AwaitingReview, $imported->status);
        $this->assertTrue($imported->document_deadline->lt(now()));
        $this->assertTrue($imported->documents()->firstOrFail()->received_at->lte($imported->document_deadline));
        $this->withSession(['bc_guardian_pmd_id' => $this->pmdId])
            ->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertUnprocessable();
    }

    public function test_incomplete_delivery_cannot_be_imported_after_deadline(): void
    {
        $actor = LegacyUser::query()->findOrFail($this->sourceRequest->created_by);
        $this->expectException(ValidationException::class);
        app(PmdIntake::class)->import($this->pmdId, $this->sourceRequest->school_class_id, $actor);
    }
}

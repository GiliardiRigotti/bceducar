<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDeadlinePolicyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
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

    private function administrator(): LegacyUser
    {
        return LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
    }

    public function test_expiry_preserves_pmd_status_in_both_delivery_modes(): void
    {
        $request = RegistrationRequest::query()->where('protocol', 'BC-2026-0002')->firstOrFail();
        $workflow = app(RegistrationWorkflow::class);
        $before = DB::table('pmieducar.matricula')->count();
        foreach (AttendanceMode::cases() as $mode) {
            foreach ([PreRegistration::STATUS_WAITING, PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION] as $status) {
                DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => $status]);
                $request->refresh()->update(['status' => RequestStatus::AwaitingDocuments, 'attendance_mode' => $mode,
                    'document_deadline' => now()->subDay()]);
                $this->assertTrue($workflow->expire($request));
                $this->assertFalse($workflow->expire($request));
                $pmd = PreRegistration::query()->findOrFail($request->pmd_preregistration_id);
                $this->assertSame($status, $pmd->status);
                $this->assertSame('DEADLINE_EXPIRED', $pmd->documentation_status);
            }
        }
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
    }

    public function test_school_can_explicitly_close_expired_application_with_authorization_and_reason(): void
    {
        $otherOperator = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.operador.medici')->value('legacy_id'));
        $request = RegistrationRequest::query()->whereNotIn('school_id', RegistrationRequest::query()->visibleTo($otherOperator)->select('school_id'))
            ->where('status', RequestStatus::AwaitingDocuments->value)->whereNotNull('pmd_preregistration_id')
            ->whereHas('documents', fn ($query) => $query->where('required', true)->where('status', DocumentStatus::Pending->value))->firstOrFail();
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => PreRegistration::STATUS_WAITING]);
        $request->update(['document_deadline' => now()->subDay()]);
        app(RegistrationWorkflow::class)->expire($request);
        $expiredStatus = $request->fresh()->status->value;
        $this->actingAs($otherOperator)->post(route('bc-registration.action', $request), [
            'action' => 'reject', 'reason' => 'Sem acesso',
        ])->assertForbidden();
        $actor = $this->administrator();
        $this->actingAs($actor)->get(route('bc-registration.show', $request))->assertOk()->assertSee('Decisão após o prazo');
        $this->postJson(route('bc-registration.action', $request), ['action' => 'reject'])->assertUnprocessable();
        $this->post(route('bc-registration.action', $request), ['action' => 'reject', 'reason' => 'Decisão escolar após prazo'])
            ->assertRedirect();
        $this->assertSame(RequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame(PreRegistration::STATUS_REJECTED, PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->status);
        $event = $request->events()->where('event', 'REQUEST_REJECTED')->latest('id')->firstOrFail();
        $this->assertSame($actor->getKey(), $event->actor_id);
        $this->assertSame($expiredStatus, $event->metadata['previous_status']);
        $this->assertSame('Decisão escolar após prazo', $event->metadata['reason']);
    }

    public function test_school_can_review_on_time_delivery_after_deadline_but_cannot_receive_late_upload(): void
    {
        Storage::fake('registration-documents');
        $actor = $this->administrator();
        $this->actingAs($actor);
        $request = RegistrationRequest::query()->where('protocol', 'BC-2026-0002')->firstOrFail();
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update(['status' => PreRegistration::STATUS_SUMMONED]);
        $request->update(['status' => RequestStatus::AwaitingReview, 'document_deadline' => now()->subDay()]);
        $request->documents()->where('required', true)->update(['status' => DocumentStatus::Sent,
            'received_at' => now()->subDays(2)]);
        $workflow = app(RegistrationWorkflow::class);
        $this->assertFalse($workflow->expire($request));
        $pending = $request->documents()->where('required', false)->where('status', DocumentStatus::Pending->value)->firstOrFail();
        $this->postJson(route('bc-registration.receive', $request), [
            'document_type' => $pending->document_type->value,
            'document' => UploadedFile::fake()->image('atrasado.png'),
        ])->assertUnprocessable();
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());
        foreach ($request->documents()->where('required', true)->get() as $document) {
            $workflow->review($document, $actor, DocumentStatus::Approved);
        }
        $workflow->approve($request, $actor);
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertSame('APPROVED', PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->documentation_status);
    }
}

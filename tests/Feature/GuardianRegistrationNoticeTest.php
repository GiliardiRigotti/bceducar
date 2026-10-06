<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Mail\GuardianRegistrationNotice;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuardianRegistrationNoticeTest extends TestCase
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

    public function test_release_notice_contains_deadline_and_document_button_and_is_sent_once(): void
    {
        Mail::fake();
        config(['bc-notifications.enabled' => true]);
        $request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->firstOrFail();
        $request->update(['seed_source' => null, 'document_deadline' => now()->addDays(7)]);
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($actor);
        $mutation = app(AcceptPreRegistrations::class);
        foreach ([1, 2] as $_) {
            $mutation(null, ['ids' => [$request->pmd_preregistration_id], 'classroom' => $request->school_class_id]);
        }
        $this->assertSame(1, DB::table('bc_guardian_notifications')->where('kind', 'DOCUMENTS_OPEN')->count());
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, function ($mail) use ($request) {
            $this->assertSame('DOCUMENTS_OPEN', $mail->kind);
            $html = $mail->render();
            $this->assertStringContainsString('Pré-matrícula deferida', $html);
            $this->assertStringContainsString($request->document_deadline->format('d/m/Y H:i'), $html);
            $this->assertStringContainsString(url('/matricula-digital'), $html);
            $this->assertStringContainsString('entrega presencial', $html);

            return true;
        });
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_correction_notice_is_sent_once_after_document_review(): void
    {
        Mail::fake();
        config(['bc-notifications.enabled' => true]);
        $request = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->whereIn('status', ['DOCUMENTOS_ENVIADOS', 'AGUARDANDO_ANALISE', 'EM_ANALISE'])
            ->whereHas('documents', fn ($query) => $query->where('status', DocumentStatus::Sent->value))
            ->firstOrFail();
        $request->update(['seed_source' => null, 'document_deadline' => now()->addDays(7)]);
        $document = $request->documents()->where('status', DocumentStatus::Sent->value)->firstOrFail();
        $actorId = BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id');
        app(RegistrationWorkflow::class)->review($document, LegacyUser::query()->findOrFail($actorId),
            DocumentStatus::Correction, 'Documento ilegível');
        $this->assertDatabaseHas('bc_guardian_notifications', ['kind' => 'CORRECTION', 'attempts' => 0]);
        Mail::assertNothingOutgoing();

        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, function ($mail) use ($document) {
            $this->assertSame($document->fresh()->delivery_deadline->format('d/m/Y H:i'), $mail->deadline);
            $this->assertStringContainsString('Reentregue o documento corrigido', $mail->render());

            return true;
        });
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, 1);
        $this->assertSame(1, DB::table('bc_guardian_notifications')->where('kind', 'CORRECTION')->whereNotNull('sent_at')->count());
    }

    public function test_demo_events_do_not_queue_real_email(): void
    {
        $request = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->firstOrFail();
        app(RegistrationWorkflow::class)->event($request, EventType::Expired);
        $this->assertSame(0, DB::table('bc_guardian_notifications')->count());
    }

    public function test_deadline_reminder_is_queued_once_and_sent_before_expiry(): void
    {
        Mail::fake();
        config(['bc-notifications.enabled' => true]);
        $request = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $request->update(['seed_source' => null, 'status' => 'AGUARDANDO_DOCUMENTOS',
            'document_deadline' => now()->addHours(12)]);
        $this->artisan('bc:queue-deadline-reminders')->assertSuccessful();
        $this->artisan('bc:queue-deadline-reminders')->assertSuccessful();
        $this->assertSame(1, DB::table('bc_guardian_notifications')->where('kind', 'REMINDER')->count());
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertSent(GuardianRegistrationNotice::class, 1);
        $this->assertDatabaseHas('bc_guardian_notifications', ['kind' => 'REMINDER', 'attempts' => 1]);
    }

    public function test_expired_reminder_is_discarded_without_email(): void
    {
        Mail::fake();
        config(['bc-notifications.enabled' => true]);
        $request = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $request->update(['seed_source' => null, 'status' => 'AGUARDANDO_DOCUMENTOS',
            'document_deadline' => now()->addHours(12)]);
        $this->artisan('bc:queue-deadline-reminders')->assertSuccessful();
        $request->update(['document_deadline' => now()->subMinute()]);
        $this->artisan('bc:send-guardian-notices')->assertSuccessful();
        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('bc_guardian_notifications', [
            'kind' => 'REMINDER', 'attempts' => 5, 'last_error' => 'Lembrete desatualizado',
        ]);
    }
}

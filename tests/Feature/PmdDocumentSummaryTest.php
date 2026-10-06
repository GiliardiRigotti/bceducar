<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\PmdDocumentSummary;
use App\EnrollmentRequests\RequestAccess;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use App\Setting;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmdDocumentSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationRequest $request;

    private PreRegistration $pmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        if (!BcDemoEntity::query()->where('key', 'complete')->exists()) {
            $this->seed(BalnearioCamboriuDemoSeeder::class);
        }
        $this->request = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $this->pmd = PreRegistration::query()->findOrFail($this->request->pmd_preregistration_id);
        $this->pmd->update(['status' => PreRegistration::STATUS_SUMMONED]);
        $this->request->update(['document_deadline' => now()->addDays(7)]);
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id'));
        $this->actingAs($actor, 'web');
    }

    private function summaryQuery()
    {
        return $this->postJson('/graphql', [
            'query' => 'query($protocol: String!) { preregistrationByProtocol(protocol: $protocol) { documentarySummary { released label mode deadline required received approved corrections enrolled triageUrl } } }',
            'variables' => ['protocol' => $this->pmd->protocol],
        ])->assertOk();
    }

    public function test_native_query_exposes_authorized_summary_without_creating_enrollment(): void
    {
        $this->request->update(['status' => 'APROVADA', 'attendance_mode' => null, 'registration_id' => null]);
        $response = $this->summaryQuery();
        $this->assertNull($response->json('errors'), json_encode($response->json('errors')));
        $summary = $response->json('data.preregistrationByProtocol.documentarySummary');
        $this->assertTrue($summary['released']);
        $this->assertNull($summary['mode']);
        $this->assertFalse($summary['enrolled']);
        $this->assertStringContainsString('aguardando efetivação', $summary['label']);
        $this->assertSame(route('bc-registration.show', $this->request), $summary['triageUrl']);
        $this->assertNull($this->request->fresh()->registration_id);
    }

    public function test_public_pmd_token_cannot_read_documentary_summary(): void
    {
        auth('web')->logout();
        $token = Setting::query()->where('key', 'prematricula.token')->firstOrFail()->value;
        $this->withHeader('Authorization', 'Bearer '.$token);
        $response = $this->summaryQuery();
        $this->assertNull($response->json('errors'), json_encode($response->json('errors')));
        $this->assertNull($response->json('data.preregistrationByProtocol.documentarySummary'));
        $this->assertNull(app(PmdDocumentSummary::class)($this->pmd));
    }

    public function test_operator_from_another_school_and_inactive_user_cannot_read_summary(): void
    {
        $actor = LegacyUser::query()->findOrFail(BcDemoEntity::query()->where('key', 'user.operador.medici')->value('legacy_id'));
        $allowed = app(RequestAccess::class)->schools($actor)->pluck('cod_escola');
        $other = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->whereNotIn('school_id', $allowed)->firstOrFail();
        $pmd = PreRegistration::query()->findOrFail($other->pmd_preregistration_id);
        $this->actingAs($actor, 'web');
        $this->assertNull(app(PmdDocumentSummary::class)($pmd));
        $this->pmd = $pmd;
        $response = $this->summaryQuery();
        $this->assertNull($response->json('errors'));
        $this->assertNull($response->json('data.preregistrationByProtocol'));
        $actor->update(['ativo' => 0]);
        $this->assertNull(app(PmdDocumentSummary::class)($this->pmd));
    }

    public function test_waiting_link_never_presents_documentation_as_released(): void
    {
        $this->pmd->update(['status' => PreRegistration::STATUS_WAITING]);
        $summary = app(PmdDocumentSummary::class)($this->pmd);
        $this->assertFalse($summary['released']);
        $this->assertNull($summary['triageUrl']);
        $this->assertNull($summary['deadline']);
        $this->assertSame(0, $summary['received']);
    }

    public function test_unlinked_legacy_preregistration_keeps_nullable_contract(): void
    {
        $this->request->update(['pmd_preregistration_id' => null]);
        $response = $this->summaryQuery();
        $this->assertNull($response->json('errors'), json_encode($response->json('errors')));
        $this->assertNull($response->json('data.preregistrationByProtocol.documentarySummary'));
    }

    public function test_progress_excludes_replaced_versions_and_optional_documents(): void
    {
        $documents = $this->request->documents()->where('status', '!=', DocumentStatus::Replaced->value)->get();
        $this->assertNotEmpty($documents);
        foreach ($documents as $document) {
            $document->update(['required' => true, 'status' => DocumentStatus::Approved, 'received_at' => now()]);
        }
        $first = $documents->first();
        $first->update(['status' => DocumentStatus::Correction]);
        foreach ([true, false] as $required) {
            $copy = $first->replicate();
            $copy->required = $required;
            $copy->status = $required ? DocumentStatus::Replaced : DocumentStatus::Approved;
            $copy->save();
        }
        $summary = app(PmdDocumentSummary::class)($this->pmd);
        $this->assertSame($documents->count(), $summary['required']);
        $this->assertSame($documents->count(), $summary['received']);
        $this->assertSame($documents->count() - 1, $summary['approved']);
        $this->assertSame(1, $summary['corrections']);
    }

    public function test_late_receipts_do_not_count_as_valid_progress(): void
    {
        $documents = $this->request->documents()->where('status', '!=', DocumentStatus::Replaced->value)->get();
        $this->assertNotEmpty($documents);
        foreach ($documents as $document) {
            $document->update(['required' => true, 'status' => DocumentStatus::Approved,
                'received_at' => $this->request->document_deadline->copy()->addMinute()]);
        }
        $summary = app(PmdDocumentSummary::class)($this->pmd);
        $this->assertSame($documents->count(), $summary['required']);
        $this->assertSame(0, $summary['received']);
        $this->assertSame(0, $summary['approved']);
    }
}

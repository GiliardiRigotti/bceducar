<?php

namespace Tests\Feature;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\PmdIntake;
use App\Mail\GuardianAccessCode;
use App\Models\BcDemoEntity;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuardianDocumentTest extends TestCase
{
    use DatabaseTransactions;

    private array $uploadFixtures = [];

    private function realUpload(string $name, string $content): UploadedFile
    {
        $fixture = UploadedFile::fake()->createWithContent($name, $content);
        $this->uploadFixtures[] = $fixture;

        // Spoofed client MIME must not override MIME detected from the actual bytes.
        return new UploadedFile($fixture->getRealPath(), $name, 'image/png', null, true);
    }

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

    public function test_access_email_contains_the_registered_protocol_and_tracking_link(): void
    {
        Mail::fake();
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $pmd = PreRegistration::query()->with('responsible')->findOrFail($application->pmd_preregistration_id);
        $this->post('/matricula-digital/acesso', [
            'protocol' => $pmd->protocol, 'email' => $pmd->responsible->email,
        ])->assertRedirect();
        Mail::assertSent(GuardianAccessCode::class, function ($mail) use ($pmd) {
            $mail->assertSeeInText($pmd->protocol);
            $mail->assertSeeInText(url('/matricula-digital'));

            return $mail->protocol === $pmd->protocol;
        });
    }

    public function test_unverified_visitor_cannot_read_documents_or_submit(): void
    {
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $document = $application->documents()->firstOrFail();
        $this->get('/matricula-digital')->assertOk()->assertSee('Acesso do responsável');
        $this->get('/matricula-digital/documentos/' . $document->id . '/download')->assertForbidden();
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertForbidden();
        Mail::fake();
        $this->post('/matricula-digital/acesso', ['protocol' => $application->source_reference, 'email' => 'errado@teste.bc.local'])
            ->assertRedirect();
        Mail::assertNothingOutgoing();
    }

    public function test_verified_guardian_can_change_mode_without_resetting_deadline_and_upload_privately(): void
    {
        Storage::fake('registration-documents');
        $application = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->where('status', 'AGUARDANDO_DOCUMENTOS')
            ->orderBy('id')->firstOrFail();
        $application->update(['document_deadline' => now()->addDays(7)]);
        $document = $application->documents()->where('status', DocumentStatus::Pending->value)->firstOrFail();
        $deadline = $application->document_deadline->toDateTimeString();
        $challenge = (string) Str::uuid();
        Cache::put('bc-guardian:' . $challenge, [
            'pmd_id' => $application->pmd_preregistration_id,
            'hash' => hash_hmac('sha256', '123456', config('app.key')),
        ], now()->addMinutes(10));
        $this->withSession(['bc_guardian_challenge' => $challenge]);
        $this->post('/matricula-digital/verificar', ['code' => '123456'])->assertRedirect('/matricula-digital');
        $this->get('/matricula-digital')->assertOk()->assertSee($application->protocol);

        $this->post('/matricula-digital/modalidade', ['attendance_mode' => AttendanceMode::InPerson->value])->assertRedirect();
        $this->assertSame($deadline, $application->fresh()->document_deadline->toDateTimeString());
        $this->assertDatabaseHas('preregistrations', [
            'id' => $application->pmd_preregistration_id, 'document_submission_method' => 'IN_PERSON',
        ]);
        $this->assertDatabaseHas('bc_registration_events', [
            'registration_request_id' => $application->id, 'event' => 'ATTENDANCE_MODE_CHANGED', 'actor_type' => 'GUARDIAN',
        ]);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => AttendanceMode::Online->value])->assertRedirect();
        $this->post('/matricula-digital/documentos', [
            'document_type' => $document->document_type->value,
            'document' => UploadedFile::fake()->image('documento.png'),
        ])->assertRedirect();
        $sent = $document->fresh();
        $this->assertSame(DocumentStatus::Sent, $sent->status);
        $this->assertSame(64, strlen($sent->sha256));
        Storage::disk('registration-documents')->assertExists($sent->path);
        $this->get('/matricula-digital/documentos/' . $sent->id . '/download')->assertOk();
        $this->assertSame($deadline, $application->fresh()->document_deadline->toDateTimeString());
    }

    public function test_code_is_only_sent_to_registered_responsible_email(): void
    {
        Mail::fake();
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $pmd = PreRegistration::query()->with('responsible')->findOrFail($application->pmd_preregistration_id);
        $this->post('/matricula-digital/acesso', [
            'protocol' => $pmd->protocol,
            'email' => $pmd->responsible->email,
        ])->assertRedirect();
        Mail::assertSentCount(1);
        $this->assertIsString(session('bc_guardian_challenge'));
    }

    public function test_guardian_can_upload_after_school_release_and_file_survives_import(): void
    {
        Storage::fake('registration-documents');
        config(['bc-documents.max_upload_kb' => 1024]);
        $existing = RegistrationRequest::query()->where('seed_source', BalnearioCamboriuDemoSeeder::SOURCE)
            ->whereNotNull('pmd_preregistration_id')->where('status', 'AGUARDANDO_DOCUMENTOS')
            ->orderBy('id')->firstOrFail();
        $pmdId = $existing->pmd_preregistration_id;
        $existing->update(['pmd_preregistration_id' => null]);
        DB::table('preregistration_documents')->where('preregistration_id', $pmdId)->delete();
        DB::table('preregistrations')->where('id', $pmdId)->update([
            'status' => PreRegistration::STATUS_SUMMONED,
            'document_submission_method' => null, 'documentation_status' => null,
            'documentation_deadline' => now()->addDay(),
        ]);
        $typeId = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Nascimento', 'code' => 'CERTIDAO_NASCIMENTO', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert([
            'process_id' => PreRegistration::query()->findOrFail($pmdId)->process_id,
            'document_type_id' => $typeId, 'required' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withSession(['bc_guardian_pmd_id' => $pmdId]);
        $this->get('/matricula-digital')->assertOk();
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertRedirect();
        $this->get('/matricula-digital')->assertOk()->assertSee('1,00 MB');
        foreach ([
            $this->realUpload('imagem.png', '<?php echo "executavel";'),
            $this->realUpload('imagem.txt', UploadedFile::fake()->image('real.png')->get()),
        ] as $file) {
            $this->postJson('/matricula-digital/documentos', ['document_type' => 'CERTIDAO_NASCIMENTO', 'document' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('document');
            $this->assertSame([], Storage::disk('registration-documents')->allFiles());
        }

        $this->postJson('/matricula-digital/documentos', [
            'document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('grande.png')->size(1025),
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());
        $this->post('/matricula-digital/documentos', [
            'document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => $this->realUpload('cedula.png', UploadedFile::fake()->image('real.png')->get()),
        ])->assertRedirect();
        $source = DB::table('preregistration_documents')->where('preregistration_id', $pmdId)->firstOrFail();
        $this->assertSame('PENDING', $source->status);
        Storage::disk('registration-documents')->assertExists($source->file_path);
        $this->get('/matricula-digital/pre-documentos/' . $source->id . '/download')->assertOk();
        $other = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $this->withSession(['bc_guardian_pmd_id' => $other->pmd_preregistration_id]);
        $this->get('/matricula-digital/pre-documentos/' . $source->id . '/download')->assertNotFound();
        $this->withSession(['bc_guardian_pmd_id' => $pmdId]);
        $this->withSession(['bc_guardian_pmd_id' => null]);
        $this->get('/matricula-digital/pre-documentos/' . $source->id . '/download')->assertForbidden();
        $this->withSession(['bc_guardian_pmd_id' => $pmdId]);
        $this->assertDatabaseHas('preregistration_document_events', [
            'preregistration_id' => $pmdId, 'event' => 'DOCUMENT_UPLOADED',
        ]);

        $actor = LegacyUser::query()->findOrFail($existing->created_by);
        $imported = app(PmdIntake::class)->import($pmdId, $existing->school_class_id, $actor);
        $document = $imported->documents()->where('document_type', 'CERTIDAO_NASCIMENTO')->firstOrFail();
        $this->assertSame($source->file_path, $document->path);
        $this->assertSame($source->hash, $document->sha256);
        $this->assertSame(AttendanceMode::Online, $imported->attendance_mode);
        $this->assertSame(DocumentStatus::Sent, $document->status);
        $this->assertSame(Carbon::parse(PreRegistration::query()->findOrFail($pmdId)->documentation_deadline)->toDateTimeString(),
            $imported->document_deadline->toDateTimeString());
        $this->get('/matricula-digital/documentos/' . $document->id . '/download')->assertOk();
    }

    public function test_waiting_preregistration_cannot_start_documentation(): void
    {
        Storage::fake('registration-documents');
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->firstOrFail();
        $id = $application->pmd_preregistration_id;
        $application->update(['pmd_preregistration_id' => null]);
        DB::table('preregistrations')->where('id', $id)->update(['status' => PreRegistration::STATUS_WAITING]);
        $this->withSession(['bc_guardian_pmd_id' => $id]);
        $this->get('/matricula-digital')->assertOk()->assertSee('Aguarde a análise da escola')
            ->assertDontSee('name="document"', false)->assertDontSee('Salvar forma de entrega');
        $this->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])
            ->assertUnprocessable()->assertJsonValidationErrors('documents');
        $this->postJson('/matricula-digital/documentos', [
            'document_type' => 'CERTIDAO_NASCIMENTO', 'document' => UploadedFile::fake()->image('teste.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('documents');
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());
    }

    public function test_waiting_list_only_opens_documents_after_explicit_school_deferment(): void
    {
        $existing = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->firstOrFail();
        $id = $existing->pmd_preregistration_id;
        $existing->update(['pmd_preregistration_id' => null]);
        DB::table('preregistrations')->where('id', $id)->update([
            'status' => PreRegistration::STATUS_WAITING, 'preregistration_type_id' => PreRegistration::WAITING_LIST,
            'documentation_deadline' => now()->addDays(7),
        ]);
        $this->withSession(['bc_guardian_pmd_id' => $id]);
        $this->get('/matricula-digital')->assertOk()->assertDontSee('name="document"', false);
        $actor = LegacyUser::query()->findOrFail($existing->created_by);
        $this->actingAs($actor);
        $before = DB::table('pmieducar.matricula')->count();
        app(AcceptPreRegistrations::class)(null,
            ['ids' => [$id], 'classroom' => $existing->school_class_id]);
        $this->assertSame($before, DB::table('pmieducar.matricula')->count());
        $this->get('/matricula-digital')->assertOk()->assertSee('Pré-matrícula deferida: aguardando documentação');
        Mail::fake();
        $pmd = PreRegistration::query()->with('responsible')->findOrFail($id);
        $this->post('/matricula-digital/acesso', ['protocol' => $pmd->protocol, 'email' => $pmd->responsible->email])->assertRedirect();
        Mail::assertSentCount(1);
    }

    public static function uploadChannels(): array
    {
        return ['responsável' => ['guardian'], 'operador' => ['operator']];
    }

    #[DataProvider('uploadChannels')]
    public function test_configured_upload_boundary_is_shared_by_guardian_and_school(string $channel): void
    {
        Storage::fake('registration-documents');
        config(['bc-documents.max_upload_kb' => 64]);
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->where('attendance_mode', 'ONLINE')
            ->firstOrFail();
        $application->update(['document_deadline' => now()->addDays(7)]);
        $document = $application->documents()->where('status', DocumentStatus::Pending->value)->firstOrFail();
        $deadline = $application->document_deadline->toDateTimeString();
        if ($channel === 'guardian') {
            $this->withSession(['bc_guardian_pmd_id' => $application->pmd_preregistration_id]);
            $endpoint = '/matricula-digital/documentos';
            $page = '/matricula-digital';
        } else {
            $actorId = BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id');
            $this->actingAs(LegacyUser::query()->findOrFail($actorId));
            $endpoint = route('bc-registration.receive', $application);
            $page = route('bc-registration.show', $application);
        }
        $this->get($page)->assertOk()->assertSee('0,06 MB');
        $this->postJson($endpoint, [
            'document_type' => $document->document_type->value,
            'document' => UploadedFile::fake()->image('acima.png')->size(65),
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertSame([], Storage::disk('registration-documents')->allFiles());
        $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
        $this->post($endpoint, [
            'document_type' => $document->document_type->value,
            'document' => UploadedFile::fake()->image('limite.png')->size(64),
        ])->assertRedirect();
        $this->assertSame(DocumentStatus::Sent, $document->fresh()->status);
        Storage::disk('registration-documents')->assertExists($document->fresh()->path);
        $this->assertSame($deadline, $application->fresh()->document_deadline->toDateTimeString());
    }

    public function test_guardian_cannot_read_or_replace_another_guardians_document(): void
    {
        Storage::fake('registration-documents');
        $own = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->firstOrFail();
        $own->update(['attendance_mode' => 'ONLINE', 'document_deadline' => now()->addDay()]);
        $other = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')->whereKeyNot($own->id)->firstOrFail();
        $document = $other->documents()->firstOrFail();
        $document->update(['path' => 'testing/other.png']);
        Storage::disk('registration-documents')->put('testing/other.png', 'private');
        $before = $document->fresh()->getAttributes();
        $this->withSession(['bc_guardian_pmd_id' => $own->pmd_preregistration_id]);
        $this->get('/matricula-digital/documentos/' . $document->id . '/download')->assertForbidden();
        $this->postJson('/matricula-digital/documentos', [
            'document_type' => $document->document_type->value, 'replaces_id' => $document->id,
            'document' => UploadedFile::fake()->image('novo.png'),
        ])->assertNotFound();
        $this->assertSame($before, $document->fresh()->getAttributes());
        $this->assertSame(['testing/other.png'], Storage::disk('registration-documents')->allFiles());
    }

    #[DataProvider('uploadChannels')]
    public function test_disguised_executable_and_svg_uploads_are_rejected(string $channel): void
    {
        Storage::fake('registration-documents');
        $application = RegistrationRequest::query()->whereNotNull('pmd_preregistration_id')
            ->where('status', 'AGUARDANDO_DOCUMENTOS')->firstOrFail();
        $application->update(['attendance_mode' => 'ONLINE', 'document_deadline' => now()->addDay()]);
        $document = $application->documents()->where('status', DocumentStatus::Pending->value)->firstOrFail();
        if ($channel === 'guardian') {
            $this->withSession(['bc_guardian_pmd_id' => $application->pmd_preregistration_id]);
            $endpoint = '/matricula-digital/documentos';
        } else {
            $this->actingAs(LegacyUser::query()->findOrFail($application->created_by));
            $endpoint = route('bc-registration.receive', $application);
        }
        foreach ([
            $this->realUpload('imagem.png', '<?php echo "executavel";'),
            $this->realUpload('documento.pdf', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            $this->realUpload('imagem.php', UploadedFile::fake()->image('real.png')->get()),
            $this->realUpload('imagem.txt', UploadedFile::fake()->image('real.png')->get()),
        ] as $file) {
            $this->postJson($endpoint, ['document_type' => $document->document_type->value, 'document' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('document');
            $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
            $this->assertSame([], Storage::disk('registration-documents')->allFiles());
        }
    }
}

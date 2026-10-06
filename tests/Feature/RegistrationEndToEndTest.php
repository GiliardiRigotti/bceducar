<?php

namespace Tests\Feature;

use App\EnrollmentRequests\DeclaredStudentData;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\PhysicalConfirmation;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\Models\BcDemoEntity;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\RegistrationRequest;
use App\Setting;
use Carbon\Carbon;
use Database\Seeders\BalnearioCamboriuDemoSeeder;
use iEducar\Packages\PreMatricula\Exceptions\PreRegistrationValidationException;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\NewPreRegistration;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationEndToEndTest extends TestCase
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

    public function test_public_pmd_signup_to_native_registration(): void
    {
        Mail::fake();
        Storage::fake('registration-documents');
        $processId = BcDemoEntity::query()->where('key', 'pmd.process')->value('legacy_id');
        $stageId = BcDemoEntity::query()->where('key', 'pmd.stage.2')->value('legacy_id');
        $vacancy = DB::table('process_vacancy')->where('process_id', $processId)
            ->where('available', '>', 2)->orderBy('available', 'desc')->firstOrFail();
        DB::table('process_stages')->where('id', $stageId)->update(['allow_waiting_list' => true]);
        DB::table('processes')->where('id', $processId)->update(['waiting_list_limit' => 2]);
        $alternatives = DB::table('process_vacancy')->where('process_id', $processId)
            ->where('grade_id', $vacancy->grade_id)->where('period_id', $vacancy->period_id)
            ->where('school_id', '!=', $vacancy->school_id)->distinct()->limit(2)->pluck('school_id');
        $this->assertCount(2, $alternatives);
        $year = DB::table('processes')->where('id', $processId)->value('school_year_id');
        $class = LegacySchoolClass::query()->where('ref_ref_cod_escola', $vacancy->school_id)
            ->where('ref_ref_cod_serie', $vacancy->grade_id)->where('turma_turno_id', $vacancy->period_id)
            ->where('ano', $year)->where('ativo', 1)->firstOrFail();
        $typeId = DB::table('preregistration_document_types')->insertGetId([
            'name' => 'Certidão de nascimento', 'code' => 'CERTIDAO_NASCIMENTO', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('process_document_types')->insert([
            'process_id' => $processId, 'document_type_id' => $typeId, 'required' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unique = Str::upper(Str::random(12));
        $email = Str::lower($unique) . '@teste.bc.local';
        $token = Setting::query()->where('key', 'prematricula.token')->firstOrFail()->value;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/graphql', [
            'query' => 'mutation ($input: PreRegistrationInput!) { newPreRegistration(input: $input) { id protocol responsible { email addresses { address lat lng } } } }',
            'variables' => ['input' => [
                'type' => 'REGISTRATION', 'process' => (string) $processId, 'stage' => (string) $stageId,
                'grade' => (string) $vacancy->grade_id, 'period' => (string) $vacancy->period_id,
                'school' => (string) $vacancy->school_id,
                'optionalSchool' => (string) $alternatives[0], 'optionalPeriod' => (string) $vacancy->period_id,
                'waitingList' => [],
                'relationType' => 'MOTHER',
                'address' => ['postalCode' => '88330000', 'address' => 'Rua Teste BC', 'number' => '100',
                    'neighborhood' => 'Centro', 'city' => 'Balneário Camboriú', 'stateAbbreviation' => 'SC',
                    'cityIbgeCode' => 4202008, 'manualChangeLocation' => false],
                'student' => [
                    ['field' => 'student_name', 'value' => 'Aluno Teste ' . $unique],
                    ['field' => 'student_date_of_birth', 'value' => '2019-04-12'],
                    ['field' => 'student_gender', 'value' => '2'],
                ],
                'responsible' => [
                    ['field' => 'responsible_name', 'value' => 'Responsável Teste ' . $unique],
                    ['field' => 'responsible_date_of_birth', 'value' => '1987-05-20'],
                    ['field' => 'responsible_gender', 'value' => '1'],
                    ['field' => 'responsible_email', 'value' => $email],
                ],
            ]],
        ])->assertOk();
        $this->assertNull($response->json('errors'), json_encode($response->json('errors')));
        $this->assertCount(2, $response->json('data.newPreRegistration'));
        $created = $response->json('data.newPreRegistration.0');
        $this->assertNotEmpty($created['protocol']);
        $this->assertSame($email, $created['responsible']['email']);
        $this->assertNull($created['responsible']['addresses'][0]['lat']);
        $this->assertNull($created['responsible']['addresses'][0]['lng']);
        $this->assertSame(1, PreRegistration::query()->where('parent_id', $created['id'])
            ->where('preregistration_type_id', PreRegistration::WAITING_LIST)->count());
        $pmdId = (int) $created['id'];
        $address = PreRegistration::query()->findOrFail($pmdId)->responsible->addresses()->firstOrFail();
        $this->assertSame('Rua Teste BC', $address->address);
        $this->assertNull($address->latitude);
        $this->assertNull($address->longitude);
        $this->assertFalse(RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->exists());

        Auth::shouldUse('web');
        $this->post('/matricula-digital/acesso', ['protocol' => $created['protocol'], 'email' => $email])->assertRedirect();
        Mail::assertSentCount(1);
        $challenge = session('bc_guardian_challenge');
        $this->assertNotNull(Cache::get('bc-guardian:' . $challenge));
        // The code itself is intentionally never stored in clear text. Simulate successful OTP verification.
        Cache::put('bc-guardian:' . $challenge, ['pmd_id' => $pmdId,
            'hash' => hash_hmac('sha256', '123456', config('app.key'))], now()->addMinutes(10));
        $this->post('/matricula-digital/verificar', ['code' => '123456'])->assertRedirect('/matricula-digital');
        $this->get('/matricula-digital')->assertOk()->assertSee('Aguarde a análise da escola');
        $this->postJson('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])
            ->assertUnprocessable()->assertJsonValidationErrors('documents');
        $actorId = BcDemoEntity::query()->where('key', 'user.admin.seduc')->value('legacy_id');
        $actor = LegacyUser::query()->findOrFail($actorId);
        $this->actingAs($actor);
        app(AcceptPreRegistrations::class)(null, ['ids' => [$pmdId], 'classroom' => $class->getKey()]);
        $application = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->firstOrFail();
        $this->assertNull($application->registration_id);
        $query = $this->postJson('/graphql', ['query' => 'query($protocol: String) { preregistrationByProtocol(protocol: $protocol) { status documentationStatus documentationDeadline } }', 'variables' => ['protocol' => $created['protocol']]])->assertOk();
        $this->assertNull($query->json('errors'), json_encode($query->json('errors')));
        $this->assertSame('SUMMONED', $query->json('data.preregistrationByProtocol.status'));
        $this->assertSame('AWAITING_DOCUMENTS', $query->json('data.preregistrationByProtocol.documentationStatus'));
        $this->assertNotEmpty($query->json('data.preregistrationByProtocol.documentationDeadline'));
        Auth::shouldUse('web');
        $this->assertDatabaseHas('bc_guardian_notifications', ['kind' => 'DOCUMENTS_OPEN']);
        $this->assertSame(PreRegistration::STATUS_SUMMONED, PreRegistration::query()->findOrFail($pmdId)->status);
        $this->post('/matricula-digital/modalidade', ['attendance_mode' => 'ONLINE'])->assertRedirect();
        $this->post('/matricula-digital/documentos', [
            'document_type' => 'CERTIDAO_NASCIMENTO',
            'document' => UploadedFile::fake()->image('certidao.png'),
        ])->assertRedirect();
        $document = $application->documents()->where('required', true)->firstOrFail()->fresh();
        Storage::disk('registration-documents')->assertExists($document->path);
        $this->assertSame(DocumentStatus::Sent, $document->status);
        $this->actingAs($actor);
        app(RegistrationWorkflow::class)->review($document, $actor, DocumentStatus::Approved);
        app(DeclaredStudentData::class)->review($application, $actor, true, null);
        app(RegistrationWorkflow::class)->approve($application, $actor);
        $application->refresh();
        $this->assertSame(2, $application->workflow_version);
        $this->assertNull($application->registration_id);
        $this->assertSame(11, DB::table('pmieducar.matricula')->where('cod_matricula', $application->intermediate_registration_id)->value('aprovado'));
        $this->assertFalse(DB::table('pmieducar.matricula_turma')->where('ref_cod_matricula', $application->intermediate_registration_id)->exists());
        $physical = app(PhysicalConfirmation::class);
        $physical->review($application, $actor, 'document:' . $document->id, true, null);
        $physical->review($application, $actor, 'cadastro', true, null);
        $registration = app(RegistrationWorkflow::class)->finalize($application, $actor);
        $this->assertSame($registration->getKey(), $application->fresh()->registration_id);
        $this->assertSame(PreRegistration::STATUS_ACCEPTED, PreRegistration::query()->findOrFail($pmdId)->status);
        $this->assertSame(1, DB::table('pmieducar.matricula')->where('cod_matricula', $registration->getKey())->count());
        $this->assertSame(1, DB::table('pmieducar.matricula_turma')->where('ref_cod_matricula', $registration->getKey())->where('ativo', 1)->count());
    }

    public function test_missing_or_invalid_responsible_email_does_not_create_records(): void
    {
        $process = BcDemoEntity::query()->where('key', 'pmd.process')->value('legacy_id');
        $stage = BcDemoEntity::query()->where('key', 'pmd.stage.2')->value('legacy_id');
        $before = DB::table('preregistrations')->count();
        $people = DB::table('people')->count();
        foreach ([null, 'invalid-email'] as $email) {
            try {
                app(NewPreRegistration::class)(null, [
                    'process_id' => $process, 'process_stage_id' => $stage,
                    'preregistration_type_id' => PreRegistration::REGISTRATION,
                    'student' => [], 'responsible' => $email === null ? [] : [
                        ['field' => 'responsible_email', 'value' => $email],
                    ],
                ]);
                $this->fail('A valid responsible email must be required.');
            } catch (PreRegistrationValidationException $error) {
                $this->assertStringContainsString('e-mail', $error->getExtensions()['message']);
            }
            $this->assertSame($before, DB::table('preregistrations')->count());
            $this->assertSame($people, DB::table('people')->count());
        }
    }

    public function test_invalid_school_alternatives_create_no_preregistrations(): void
    {
        $process = BcDemoEntity::query()->where('key', 'pmd.process')->value('legacy_id');
        $stage = BcDemoEntity::query()->where('key', 'pmd.stage.2')->value('legacy_id');
        DB::table('process_stages')->where('id', $stage)->update(['allow_waiting_list' => true]);
        $vacancy = DB::table('process_vacancy')->where('process_id', $process)->firstOrFail();
        $base = ['process_id' => $process, 'process_stage_id' => $stage,
            'preregistration_type_id' => PreRegistration::REGISTRATION,
            'school_id' => $vacancy->school_id, 'grade_id' => $vacancy->grade_id,
            'period_id' => $vacancy->period_id, 'optionalPeriod' => $vacancy->period_id];
        $before = DB::table('preregistrations')->count();
        $people = DB::table('people')->count();
        foreach ([
            ['optionalSchool' => $vacancy->school_id],
            ['optionalSchool' => 999999999],
            ['waitingList' => array_fill(0, 2, ['school_id' => $vacancy->school_id, 'period_id' => $vacancy->period_id])],
        ] as $invalid) {
            try {
                app(NewPreRegistration::class)(null, $base + $invalid);
                $this->fail('Invalid alternatives must be refused.');
            } catch (PreRegistrationValidationException $error) {
                $this->assertNotEmpty($error->getExtensions()['validation']['message']);
            }
            $this->assertSame($before, DB::table('preregistrations')->count());
            $this->assertSame($people, DB::table('people')->count());
        }
    }
}

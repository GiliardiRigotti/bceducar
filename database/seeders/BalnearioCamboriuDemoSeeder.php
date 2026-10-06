<?php

namespace Database\Seeders;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\DocumentStatus;
use App\EnrollmentRequests\DocumentType;
use App\EnrollmentRequests\EventType;
use App\EnrollmentRequests\RegistrationWorkflow;
use App\EnrollmentRequests\RequestStatus;
use App\Geo\BcSchoolLocations;
use App\Models\BcDemoEntity;
use App\Models\City;
use App\Models\LegacyDeficiency;
use App\Models\LegacyEmployee;
use App\Models\LegacyEnrollment;
use App\Models\LegacyIndividual;
use App\Models\LegacyInstitution;
use App\Models\LegacySchool;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\LegacyUserType;
use App\Models\PersonHasPlace;
use App\Models\RegistrationDocument;
use App\Models\RegistrationEvent;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Database\Factories\LegacyCourseFactory;
use Database\Factories\LegacyEmployeeFactory;
use Database\Factories\LegacyEnrollmentFactory;
use Database\Factories\LegacyGradeFactory;
use Database\Factories\LegacyIndividualFactory;
use Database\Factories\LegacyInstitutionFactory;
use Database\Factories\LegacyOrganizationFactory;
use Database\Factories\LegacyPeriodFactory;
use Database\Factories\LegacyPersonFactory;
use Database\Factories\LegacyRegistrationFactory;
use Database\Factories\LegacySchoolAcademicYearFactory;
use Database\Factories\LegacySchoolClassFactory;
use Database\Factories\LegacySchoolFactory;
use Database\Factories\LegacyStageTypeFactory;
use Database\Factories\LegacyStudentFactory;
use Database\Factories\LegacyUserFactory;
use Database\Factories\LegacyUserSchoolFactory;
use Database\Factories\LegacyUserTypeFactory;
use Database\Factories\PlaceFactory;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BalnearioCamboriuDemoSeeder extends Seeder
{
    public const SOURCE = 'BC_DEMO_2026';

    public const SCHOOLS = [
        'medici' => 'CEM Presidente Médici', 'ivosilveira' => 'CEM Governador Ivo Silveira',
        'tomaz' => 'CEM Tomaz Francisco Garcia', 'caic' => 'CEM CAIC', 'aririba' => 'CEM Ariribá',
        'donalili' => 'CEM Dona Lili', 'novaesperanca' => 'CEM Nova Esperança', 'taquaras' => 'CEM Taquaras',
        'nei.bomsucesso' => 'NEI Bom Sucesso', 'nei.cristoluz' => 'NEI Cristo Luz', 'nei.pioneiros' => 'NEI Pioneiros',
        'nei.carrossel' => 'NEI Carrossel', 'nei.sonho' => 'NEI Sonho de Criança', 'nei.taquaras' => 'NEI Taquaras',
        'nei.sementes' => 'NEI Sementes do Amanhã',
    ];

    public const NEIGHBORHOODS = ['Centro', 'Nações', 'Municípios', 'Vila Real', 'Nova Esperança', 'Barra',
        'São Judas Tadeu', 'Ariribá', 'Pioneiros', 'Estados', 'Praia dos Amores', 'Taquaras', 'Estaleiro', 'Estaleirinho'];

    private Carbon $reference;

    private RegistrationWorkflow $workflow;

    private array $schools = [];

    private array $classes = [];

    private array $guardians = [];

    private array $students = [];

    private array $operators = [];

    private array $studentClasses = [];

    private LegacyUser $admin;

    private LegacyInstitution $institution;

    public function run(): void
    {
        if (!app()->environment(['local', 'testing', 'staging', 'homologation'])) {
            throw new RuntimeException('BalnearioCamboriuDemoSeeder só pode executar em desenvolvimento/homologação; produção é proibida.');
        }
        $this->reference = Carbon::parse(config('bc-demo.reference_date'), 'America/Sao_Paulo')->startOfDay();
        $this->workflow = app(RegistrationWorkflow::class);
        $previousTime = Carbon::getTestNow();
        try {
            Carbon::setTestNow($this->reference);
            fake()->seed(20261003);
            DB::transaction(function () {
                // Lock shared by all executions. A successful seed is never cleaned or duplicated.
                DB::select('SELECT pg_advisory_xact_lock(20261003)');
                if ($this->entityId('complete')) {
                    $this->ensureSchoolCoverage();
                    if (class_exists(PreRegistration::class)) {
                        $this->call(BalnearioCamboriuPmdSeeder::class);
                    }
                    $this->validateSeed();

                    return;
                }
                $this->createInstitution();
                $this->createSchoolsAndClasses();
                $this->createPeople();
                $this->createExistingRegistrations();
                $this->createRequests();
                $this->ensureSchoolCoverage();
                if (class_exists(PreRegistration::class)) {
                    $this->call(BalnearioCamboriuPmdSeeder::class);
                }
                $this->validateSeed();
                $this->remember('complete', 1);
            });
        } finally {
            Carbon::setTestNow($previousTime);
        }
        $this->report();
    }

    private function entityId(string $key): ?int
    {
        return BcDemoEntity::query()->where('source', self::SOURCE)->where('key', $key)->value('legacy_id');
    }

    private function remember(string $key, int $id): void
    {
        BcDemoEntity::query()->create(['source' => self::SOURCE, 'key' => $key, 'legacy_id' => $id]);
    }

    private function person(string $name, string $email, string $birth, array $extra = []): LegacyIndividual
    {
        $person = LegacyPersonFactory::new()->create(['nome' => $name, 'email' => $email, 'tipo' => 'F',
            'situacao' => 'A', 'origem_gravacao' => 'M', 'operacao' => 'I']);

        return LegacyIndividualFactory::new()->create(array_merge([
            'idpes' => $person->getKey(), 'data_nasc' => $birth, 'sexo' => 'F', 'cpf' => null, 'sus' => null,
            'idmun_nascimento' => City::query()->where('name', 'Balneário Camboriú')->value('id'),
            'operacao' => 'I', 'origem_gravacao' => 'M', 'observacao' => self::SOURCE . ' — pessoa exclusivamente fictícia',
        ], $extra));
    }

    private function user(string $login, int $level): LegacyUser
    {
        $registryLogin = $login;
        // portal.funcionario.matricula is limited to 12 characters in the native schema.
        $login = $login === 'operador.duasunidades' ? 'op.duas' : mb_substr(str_replace('operador.', 'op.', $login), 0, 12);
        // Refuse to repurpose existing accounts with the same login.
        if (LegacyEmployee::query()->where('matricula', $login)->exists()) {
            throw new RuntimeException("Login {$login} já existe e não pertence a esta execução do seed.");
        }
        $individual = $this->person('Usuário Teste ' . $login, $login . '@teste.bc.local', '1985-05-10');
        $employee = LegacyEmployeeFactory::new()->create([
            'ref_cod_pessoa_fj' => $individual->getKey(), 'matricula' => $login,
            'senha' => Hash::make(config('bc-demo.password')), 'email' => $login . '@teste.bc.local',
            'ativo' => 1, 'force_reset_password' => false, 'tempo_expira_senha' => 0,
        ]);
        $type = LegacyUserTypeFactory::new()->create([
            'nivel' => $level, 'nm_tipo' => 'BC Teste ' . $login, 'descricao' => self::SOURCE,
            'ref_funcionario_cad' => 1, 'ativo' => 1,
        ]);
        $user = LegacyUserFactory::new()->create([
            'cod_usuario' => $employee->getKey(), 'ref_cod_instituicao' => $this->institution->getKey(),
            'ref_funcionario_cad' => 1, 'ref_funcionario_exc' => null, 'ref_cod_tipo_usuario' => $type->getKey(),
            'data_cadastro' => $this->reference->copy()->subDays(60),
        ]);
        $this->remember('user.' . $registryLogin, $user->getKey());

        return $user;
    }

    private function createInstitution(): void
    {
        LegacyUserFactory::new()->current();
        $this->institution = LegacyInstitutionFactory::new()->create([
            'nm_instituicao' => 'Prefeitura Municipal de Balneário Camboriú',
            'cidade' => 'Balneário Camboriú', 'ref_sigla_uf' => 'SC', 'bairro' => 'Centro',
            'logradouro' => 'Rua Institucional Teste BC', 'cep' => '00000000',
            'nm_responsavel' => 'Gestor Teste SEDUC', 'orgao_regional' => 'SEDUC',
        ]);
        $this->remember('institution', $this->institution->getKey());
        $this->admin = $this->user('admin.seduc', LegacyUserType::LEVEL_INSTITUTIONAL);
    }

    private function createSchoolsAndClasses(): void
    {
        $periods = [LegacyPeriodFactory::new()->morning(), LegacyPeriodFactory::new()->afternoon(), LegacyPeriodFactory::new()->full()];
        $courses = [];
        foreach (['infantil' => 'Educação Infantil', 'fundamental' => 'Ensino Fundamental'] as $key => $name) {
            $course = LegacyCourseFactory::new()->withName($name)->standardAcademicYear()->create([
                'ref_cod_instituicao' => $this->institution->getKey(), 'ref_usuario_cad' => $this->admin->getKey(),
                'descricao' => self::SOURCE, 'qtd_etapas' => $key === 'infantil' ? 6 : 9,
                'importar_curso_pre_matricula' => true,
            ]);
            $this->remember('course.' . $key, $course->getKey());
            $names = $key === 'infantil' ? ['Berçário I', 'Berçário II', 'Maternal I', 'Maternal II', 'Jardim I', 'Jardim II']
                : array_map(fn ($n) => $n . 'º ano', range(1, 9));
            foreach ($names as $n => $gradeName) {
                $age = $key === 'infantil' ? $n : $n + 6;
                $grade = LegacyGradeFactory::new()->withEvaluationRule()->create([
                    'ref_cod_curso' => $course->getKey(), 'ref_usuario_cad' => $this->admin->getKey(),
                    'nm_serie' => $gradeName, 'descricao' => self::SOURCE, 'etapa_curso' => $n + 1,
                    'idade_inicial' => $age, 'idade_final' => $age + 1, 'idade_ideal' => $age,
                    'concluinte' => $n === count($names) - 1 ? 2 : 1, 'dias_letivos' => 200,
                    'importar_serie_pre_matricula' => true,
                ]);
                $this->remember('grade.' . $key . '.' . $n, $grade->getKey());
            }
            $courses[$key] = $course->fresh();
        }
        foreach (self::SCHOOLS as $slug => $name) {
            $early = str_starts_with($slug, 'nei.');
            $course = $courses[$early ? 'infantil' : 'fundamental'];
            $person = LegacyPersonFactory::new()->create(['nome' => $name, 'email' => $slug . '@teste.bc.local',
                'tipo' => 'J', 'situacao' => 'A', 'origem_gravacao' => 'M', 'operacao' => 'I']);
            $organization = LegacyOrganizationFactory::new()->create(['idpes' => $person->getKey(),
                'fantasia' => $name, 'cnpj' => null, 'insc_estadual' => null, 'idpes_cad' => $this->admin->getKey()]);
            $location = app(BcSchoolLocations::class)->catalog()[$slug];
            $school = LegacySchoolFactory::new()->withCourse($course)->create([
                'ref_idpes' => $organization->getKey(), 'ref_cod_instituicao' => $this->institution->getKey(),
                'ref_usuario_cad' => $this->admin->getKey(), 'ref_idpes_gestor' => $this->admin->getKey(),
                'sigla' => 'BC' . (count($this->schools) + 1), 'latitude' => $location['latitude'], 'longitude' => $location['longitude'],
            ]);
            app(BcSchoolLocations::class)->apply($school, $location);
            $this->remember('school.' . $slug, $school->getKey());
            $this->schools[] = $school;
            $operator = $this->user('operador.' . $slug, LegacyUserType::LEVEL_SCHOOLING);
            LegacyUserSchoolFactory::new()->create(['ref_cod_usuario' => $operator->getKey(), 'ref_cod_escola' => $school->getKey()]);
            $this->operators[$school->getKey()] = $operator;
            LegacySchoolAcademicYearFactory::new()->withSchool($school)
                ->withStageType($early ? LegacyStageTypeFactory::new()->semester() : LegacyStageTypeFactory::new()->bimonthly())
                ->create(['ano' => 2026, 'ref_usuario_cad' => $this->admin->getKey()]);
            foreach ($course->grades as $grade) {
                foreach ($early ? [2] : [0, 1] as $p) {
                    $class = LegacySchoolClassFactory::new()->create([
                        'ref_ref_cod_escola' => $school->getKey(), 'ref_ref_cod_serie' => $grade->getKey(),
                        'ref_cod_curso' => $course->getKey(), 'ref_cod_instituicao' => $this->institution->getKey(),
                        'ref_usuario_cad' => $this->admin->getKey(), 'turma_turno_id' => $periods[$p]->getKey(),
                        'nm_turma' => $grade->name . ' ' . ($p === 1 ? 'B' : 'A') . ' - ' . $periods[$p]->name,
                        'ano' => 2026, 'max_aluno' => [10, 15, 20, 25, 30][count($this->classes) % 5],
                        'hora_inicial' => ['07:45', '13:15', '07:30'][$p], 'hora_final' => ['11:45', '17:15', '17:30'][$p],
                    ]);
                    $this->remember('class.' . count($this->classes), $class->getKey());
                    $this->classes[] = $class;
                }
            }
        }
        $multi = $this->user('operador.duasunidades', LegacyUserType::LEVEL_SCHOOLING);
        foreach (array_slice($this->schools, 0, 2) as $school) {
            LegacyUserSchoolFactory::new()->create(['ref_cod_usuario' => $multi->getKey(), 'ref_cod_escola' => $school->getKey()]);
        }
    }

    private function createPeople(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $number = sprintf('%03d', $i);
            $guardian = $this->person('Responsável Teste ' . $number, "responsavel{$number}@teste.bc.local", '1985-01-15');
            $this->guardians[$i] = $guardian;
            $this->remember('guardian.' . $number, $guardian->getKey());
            $city = City::query()->where('name', 'Balneário Camboriú')->firstOrFail();
            $place = PlaceFactory::new()->create([
                'city_id' => $city->getKey(), 'address' => 'Rua Teste BC ' . $number, 'number' => (string) ($i * 100),
                'complement' => 'Endereço exclusivamente fictício', 'postal_code' => '00000000',
                'neighborhood' => self::NEIGHBORHOODS[($i - 1) % count(self::NEIGHBORHOODS)],
            ]);
            PersonHasPlace::query()->create(['person_id' => $guardian->getKey(), 'place_id' => $place->getKey(), 'type' => 1]);
        }
        // Ten families with three children, thirty with two and sixty with one: exactly 150 students.
        $familyIds = [...range(1, 100), ...range(1, 40), ...range(1, 10)];
        $earlyClasses = array_values(array_filter($this->classes, fn ($class) => $class->grade->idade_inicial < 6));
        $fundamentalClasses = array_values(array_filter($this->classes, fn ($class) => $class->grade->idade_inicial >= 6));
        for ($i = 1; $i <= 150; $i++) {
            $number = sprintf('%03d', $i);
            $class = $i <= 30 ? $earlyClasses[(($i - 1) * 5) % count($earlyClasses)] : $fundamentalClasses[(($i - 31) * 5) % count($fundamentalClasses)];
            $guardian = $this->guardians[$familyIds[$i - 1]];
            $relation = ['m', 'p', 'r', 'r'][($i - 1) % 4];
            $individual = $this->person('Aluno Teste ' . $number, "aluno{$number}@teste.bc.local",
                sprintf('%d-01-15', 2026 - $class->grade->idade_inicial), [
                    'idpes_mae' => $relation === 'm' ? $guardian->getKey() : null,
                    'idpes_pai' => $relation === 'p' ? $guardian->getKey() : ($i % 10 === 0 ? $this->guardians[100]->getKey() : null),
                    'idpes_responsavel' => $relation === 'r' ? $guardian->getKey() : null,
                ]);
            $student = LegacyStudentFactory::new()->create(['ref_idpes' => $individual->getKey(), 'tipo_responsavel' => $relation,
                'ref_usuario_cad' => $this->admin->getKey(), 'ref_usuario_exc' => null, 'ref_cod_religiao' => null,
                'ativo' => 1]);
            if ($i >= 141 && $i <= 145) {
                $individual->update(['observacao' => self::SOURCE . ' — cenário AEE exclusivamente fictício']);
                $individual->deficiency()->attach(LegacyDeficiency::query()->firstOrFail()->getKey());
            }
            $this->students[$i] = ['student' => $student, 'guardian' => $guardian];
            $this->studentClasses[$i] = $class;
            $this->remember('student.' . $number, $student->getKey());
        }
    }

    private function createExistingRegistrations(): void
    {
        // Dedicated vacancy fixtures: full, one vacancy, almost full, many vacancies, empty.
        $occupancies = [0 => 10, 1 => 14, 2 => 18, 3 => 2, 4 => 0];
        $studentNumber = 31;
        foreach ($occupancies as $index => $count) {
            $class = $this->classes[$index];
            for ($n = 0; $n < $count; $n++) {
                $student = $this->students[$studentNumber]['student'];
                $student->individual->update(['data_nasc' => sprintf('%d-01-15', 2026 - $class->grade->idade_inicial)]);
                $this->studentClasses[$studentNumber++] = $class;
                $registration = LegacyRegistrationFactory::new()->withStudent($student)->create([
                    'ref_ref_cod_serie' => $class->grade_id, 'ref_ref_cod_escola' => $class->school_id,
                    'ref_cod_curso' => $class->course_id, 'ano' => 2026, 'ativo' => 1, 'ultima_matricula' => 1,
                    'ref_usuario_cad' => $this->admin->getKey(), 'data_matricula' => '2026-02-09',
                    'observacao' => self::SOURCE,
                ]);
                LegacyEnrollmentFactory::new()->active()->create(['ref_cod_matricula' => $registration->getKey(),
                    'ref_cod_turma' => $class->getKey(), 'turno_id' => $class->turma_turno_id,
                    'ref_usuario_cad' => $this->admin->getKey(), 'ref_usuario_exc' => null, 'data_enturmacao' => '2026-02-09']);
                $this->remember('existing.' . $student->getKey(), $registration->getKey());
            }
        }
        foreach (range(111, 130) as $i) {
            $class = $this->studentClasses[$i];
            $registration = LegacyRegistrationFactory::new()->withStudent($this->students[$i]['student'])->create([
                'ref_ref_cod_serie' => $class->grade_id, 'ref_ref_cod_escola' => $class->school_id,
                'ref_cod_curso' => $class->course_id, 'ano' => 2025, 'ativo' => 1, 'ultima_matricula' => 1,
                'aprovado' => $i <= 120 ? \App_Model_MatriculaSituacao::TRANSFERIDO : \App_Model_MatriculaSituacao::APROVADO,
                'ref_usuario_cad' => $this->admin->getKey(), 'observacao' => self::SOURCE,
                'data_matricula' => '2025-02-10',
            ]);
            $this->remember('previous.' . $i, $registration->getKey());
        }
    }

    private function mockPdf(int $number, DocumentType $type, int $version = 1): string
    {
        $path = 'testing/registration-documents/' . sprintf('aluno-teste-%03d-', $number) . strtolower($type->value) . '-v' . $version . '.pdf';
        // A small, valid PDF containing no personal data. Kept private on the local disk.
        $stream = 'BT /F1 18 Tf 40 750 Td (DOCUMENTO FICTICIO) Tj 0 -30 Td (AMBIENTE DE TESTES) Tj 0 -30 Td (SEM VALIDADE) Tj ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        Storage::disk('registration-documents')->put($path, $pdf);

        return $path;
    }

    private function createRequests(): void
    {
        $states = [RequestStatus::Started, RequestStatus::AwaitingDocuments, RequestStatus::DocumentsSent,
            RequestStatus::AwaitingReview, RequestStatus::UnderReview, RequestStatus::Pending, RequestStatus::Corrected,
            RequestStatus::Approved, RequestStatus::Scheduled, RequestStatus::AwaitingAttendance,
            RequestStatus::NoShow, RequestStatus::Expired, RequestStatus::Rejected, RequestStatus::Cancelled, RequestStatus::VacancyConfirmed];
        for ($n = 1; $n <= 200; $n++) {
            $i = ($n - 1) % 150 + 1;
            $data = $this->students[$i];
            $class = $this->studentClasses[$i];
            // Completed requests are distinct students without a current registration.
            $completed = $n >= 81 && $n <= 95;
            $state = $completed ? RequestStatus::Registered : $states[($n - 1) % count($states)];
            $mode = $n % 3 === 0 || in_array($state, [RequestStatus::Scheduled, RequestStatus::AwaitingAttendance, RequestStatus::NoShow])
                ? AttendanceMode::InPerson : AttendanceMode::Online;
            if ($state === RequestStatus::Expired) {
                $mode = AttendanceMode::Online;
            }
            $created = $this->reference->copy()->subDays(60 - $n % 50)->setTime(9, $n % 60);
            Carbon::setTestNow($created);
            $expired = in_array($state, [RequestStatus::Expired, RequestStatus::NoShow]);
            $deadline = $this->reference->copy()->addDays([7, 0, 1, -1, -7][$n % 5])->endOfDay();
            if ($expired) {
                $deadline = $this->reference->copy()->subDays($n % 2 ? 1 : 7)->endOfDay();
            } elseif ($deadline->lt($this->reference)) {
                $deadline = $this->reference->copy()->addDays(7)->endOfDay();
            }
            $kind = $i >= 111 && $i <= 120 ? 'TRANSFERENCIA' : ($i >= 121 && $i <= 130 ? 'REMATRICULA' : 'NOVA');
            $request = RegistrationRequest::query()->create([
                'protocol' => sprintf('BC-2026-%04d', $n), 'student_id' => $data['student']->getKey(),
                'guardian_id' => $data['guardian']->getKey(), 'school_id' => $class->school_id,
                'grade_id' => $class->grade_id, 'school_class_id' => $class->getKey(), 'school_year' => 2026,
                'attendance_mode' => $mode, 'status' => RequestStatus::Started, 'kind' => $kind,
                'document_deadline' => $deadline, 'seed_source' => self::SOURCE,
                'source' => $i <= 30 ? 'FILA_UNICA' : 'BC_TEST',
                'source_reference' => $i <= 30 ? sprintf('TEST-FU-%04d', $n) : null,
                'created_by' => $this->admin->getKey(), 'updated_by' => $this->admin->getKey(),
            ]);
            $this->workflow->event($request, EventType::Created);
            $this->workflow->event($request, EventType::ModeSelected, metadata: ['attendance_mode' => $mode->value]);
            $actor = $this->operators[$class->school_id];
            $hasDocuments = in_array($state, [RequestStatus::DocumentsSent, RequestStatus::AwaitingReview, RequestStatus::UnderReview,
                RequestStatus::Pending, RequestStatus::Corrected, RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered]);
            foreach (DocumentType::cases() as $type) {
                $required = in_array($type, DocumentType::required($kind));
                $request->documents()->create(['document_type' => $type, 'status' => DocumentStatus::Pending, 'required' => $required]);
                if (!$hasDocuments || !$required) {
                    continue;
                }
                Carbon::setTestNow($created->copy()->addDays(1));
                $document = $this->workflow->receive($request, $actor, $type, $this->mockPdf($i, $type));
                if (in_array($state, [RequestStatus::DocumentsSent, RequestStatus::AwaitingReview])) {
                    continue;
                }
                Carbon::setTestNow($created->copy()->addDays(2));
                $result = $state === RequestStatus::UnderReview ? DocumentStatus::UnderReview : DocumentStatus::Approved;
                if ($state === RequestStatus::Pending && $type === DocumentType::Residence) {
                    $result = $n % 2 ? DocumentStatus::Rejected : DocumentStatus::Correction;
                }
                if ($state === RequestStatus::Pending && $n % 2 === 0 && $type === DocumentType::Vaccination) {
                    $result = DocumentStatus::Correction;
                }
                if ($state === RequestStatus::Corrected && $type === DocumentType::Residence) {
                    $result = DocumentStatus::Correction;
                }
                $this->workflow->review($document, $actor, $result, in_array($result, [DocumentStatus::Rejected, DocumentStatus::Correction])
                    ? ['Documento de teste vencido.', 'Arquivo de teste ilegível.', 'Divergência de informação fictícia.', 'Necessário novo comprovante.'][$n % 4] : null);
                if ($state === RequestStatus::Corrected && $type === DocumentType::Residence) {
                    // Replace an actually reviewed document, keeping the previous version and all events.
                    $document->refresh();
                    Carbon::setTestNow($created->copy()->addDays(3));
                    $replacement = $this->workflow->receive($request, $actor, $type, $this->mockPdf($i, $type, 2), $document);
                    $this->workflow->review($replacement, $actor, DocumentStatus::Approved);
                }
            }
            Carbon::setTestNow($created->copy()->addDays(4));
            if (in_array($state, [RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered, RequestStatus::Corrected])) {
                $this->workflow->approve($request, $actor);
                if ($state === RequestStatus::Registered) {
                    $this->workflow->finalize($request, $actor);
                } elseif ($state === RequestStatus::VacancyConfirmed) {
                    $request->refresh()->update(['status' => RequestStatus::VacancyConfirmed]);
                }
            } elseif ($expired) {
                Carbon::setTestNow($this->reference);
                // For a digital expired request, missing documents remain visible as pending.
                $this->workflow->expire($request);
            } elseif (in_array($state, [RequestStatus::Rejected, RequestStatus::Cancelled])) {
                $this->workflow->close($request, $actor, $state === RequestStatus::Cancelled, 'Encerramento exclusivamente fictício para testes.');
            } else {
                $request->refresh()->update(['status' => $state]);
            }
        }
        Carbon::setTestNow($this->reference);
    }

    private function ensureSchoolCoverage(): void
    {
        $schools = LegacySchool::query()->whereIn('cod_escola', BcDemoEntity::query()->where('source', self::SOURCE)
            ->where('key', 'like', 'school.%')->select('legacy_id'))->get();
        foreach ($schools as $school) {
            if (RegistrationRequest::query()->where('seed_source', self::SOURCE)->where('school_id', $school->getKey())->exists()) {
                continue;
            }
            $courseId = $school->schoolClasses()->firstOrFail()->course_id;
            $donor = RegistrationRequest::query()->where('seed_source', self::SOURCE)->where('status', RequestStatus::Started->value)
                ->whereHas('schoolClass', fn ($q) => $q->where('ref_cod_curso', $courseId))
                ->whereIn('school_id', RegistrationRequest::query()->where('seed_source', self::SOURCE)
                    ->select('school_id')->groupBy('school_id')->havingRaw('count(*) > 1'))
                ->firstOrFail();
            $class = $school->schoolClasses()->where('ref_ref_cod_serie', $donor->grade_id)->where('ano', 2026)->firstOrFail();
            $donor->update(['school_id' => $school->getKey(), 'school_class_id' => $class->getKey()]);
            $this->workflow->event($donor, EventType::SchoolAssigned, metadata: ['school_id' => $school->getKey(), 'seed_source' => self::SOURCE]);
            if ($donor->pmd_preregistration_id) {
                $pmd = PreRegistration::query()->findOrFail($donor->pmd_preregistration_id);
                $pmd->update(['school_id' => $school->getKey(), 'period_id' => $class->turma_turno_id]);
            }
        }
    }

    public function validateSeed(): void
    {
        $entities = BcDemoEntity::query()->where('source', self::SOURCE);
        foreach (['school.' => 15, 'guardian.' => 100, 'student.' => 150, 'user.operador.' => 16, 'grade.' => 15] as $prefix => $minimum) {
            if ((clone $entities)->where('key', 'like', $prefix . '%')->count() < $minimum) {
                throw new RuntimeException('Massa incompleta: ' . $prefix);
            }
        }
        $requests = RegistrationRequest::query()->where('seed_source', self::SOURCE);
        if ((clone $requests)->distinct()->count('school_id') < 15) {
            throw new RuntimeException('Todas as 15 unidades devem possuir solicitações de demonstração.');
        }
        if ((clone $requests)->count() < 200 || RegistrationDocument::query()->whereIn('registration_request_id', (clone $requests)->select('id'))->count() < 2000) {
            throw new RuntimeException('Solicitações/documentos insuficientes.');
        }
        foreach ([RequestStatus::Pending, RequestStatus::Expired, RequestStatus::Approved, RequestStatus::Registered, RequestStatus::NoShow] as $status) {
            if (!(clone $requests)->where('status', $status->value)->exists()) {
                throw new RuntimeException('Cenário ausente: ' . $status->value);
            }
        }
        foreach (AttendanceMode::cases() as $mode) {
            if (!(clone $requests)->where('attendance_mode', $mode->value)->where('status', RequestStatus::Registered->value)->exists()) {
                throw new RuntimeException('Efetivação ausente: ' . $mode->value);
            }
        }
        foreach (['TRANSFERENCIA', 'REMATRICULA'] as $kind) {
            if (!(clone $requests)->where('kind', $kind)->exists()) {
                throw new RuntimeException('Cenário ausente: ' . $kind);
            }
        }
        $classes = LegacySchoolClass::query()->whereIn('cod_turma', (clone $entities)->where('key', 'like', 'class.%')->select('legacy_id'))->get();
        $full = $vacant = false;
        foreach ($classes as $class) {
            $count = LegacyEnrollment::query()->where('ref_cod_turma', $class->getKey())->where('ativo', 1)->count();
            if ($count > $class->max_aluno) {
                throw new RuntimeException('Capacidade ultrapassada na turma ' . $class->getKey());
            }
            $full = $full || $count === (int) $class->max_aluno;
            $vacant = $vacant || $count < $class->max_aluno;
        }
        if (!$full || !$vacant) {
            throw new RuntimeException('Cenários de vagas incompletos.');
        }
    }

    private function report(): void
    {
        if (!$this->command) {
            return;
        }
        $this->command->info('BC EDUCAR — MASSA DE TESTES CRIADA / VALIDADA');
        $entities = BcDemoEntity::query()->where('source', self::SOURCE);
        foreach (['Instituição' => 'institution', 'Escolas' => 'school.', 'Cursos' => 'course.', 'Etapas' => 'grade.',
            'Turmas' => 'class.', 'Responsáveis' => 'guardian.', 'Alunos' => 'student.', 'Usuários' => 'user.'] as $label => $prefix) {
            $this->command->line($label . ': ' . (clone $entities)->where('key', 'like', $prefix . '%')->count());
        }
        $requests = RegistrationRequest::query()->where('seed_source', self::SOURCE);
        $this->command->line('Solicitações: ' . (clone $requests)->count());
        foreach (AttendanceMode::cases() as $mode) {
            $this->command->line($mode->value . ': ' . (clone $requests)->where('attendance_mode', $mode->value)->count());
        }
        foreach (RequestStatus::cases() as $status) {
            $this->command->line($status->value . ': ' . (clone $requests)->where('status', $status->value)->count());
        }
        $this->command->line('Documentos: ' . RegistrationDocument::query()->whereIn('registration_request_id', (clone $requests)->select('id'))->count());
        $this->command->line('Eventos: ' . RegistrationEvent::query()->whereIn('registration_request_id', (clone $requests)->select('id'))->count());
        if (app()->environment('local')) {
            $this->command->line('Logins: admin.seduc, op.medici, op.duas e um operador por unidade (limite nativo: 12 caracteres).');
            $this->command->line('Senha exclusivamente de teste: ' . config('bc-demo.password'));
        }
    }
}

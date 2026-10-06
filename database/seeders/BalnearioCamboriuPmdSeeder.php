<?php

namespace Database\Seeders;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\PmdBridge;
use App\Menu;
use App\Models\BcDemoEntity;
use App\Models\LegacyIndividual;
use App\Models\LegacySchoolClass;
use App\Models\LegacyUser;
use App\Models\PersonHasPlace;
use App\Models\RegistrationRequest;
use Carbon\Carbon;
use iEducar\Packages\PreMatricula\Database\Factories\PreRegistrationFactory;
use iEducar\Packages\PreMatricula\Database\Factories\ProcessFactory;
use iEducar\Packages\PreMatricula\Database\Factories\ProcessStageFactory;
use iEducar\Packages\PreMatricula\Models\Field;
use iEducar\Packages\PreMatricula\Models\Person;
use iEducar\Packages\PreMatricula\Models\PersonAddress;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Models\ProcessStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BalnearioCamboriuPmdSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing', 'staging', 'homologation'])) {
            throw new \RuntimeException('Seed PMD BC proibido em produção.');
        }
        if (!Schema::hasTable('preregistrations')) {
            throw new \RuntimeException('Execute as migrações oficiais do PMD antes da semeação.');
        }
        DB::transaction(function () {
            DB::select('SELECT pg_advisory_xact_lock(20261003)');
            $source = BalnearioCamboriuDemoSeeder::SOURCE;
            $entities = BcDemoEntity::query()->where('source', $source);
            $parentMenu = Menu::query()->where('process', 56)->firstOrFail();
            $documentationMenu = Menu::query()->firstOrCreate(['process' => 5657], [
                'parent_id' => $parentMenu->getKey(), 'title' => 'Matrículas e documentos BC',
                'link' => '/bc/matriculas', 'type' => 2, 'active' => true, 'order' => 2,
            ]);
            $menuIds = Menu::query()->whereIn('process', [56, 5656, 5657])->pluck('id');
            foreach (LegacyUser::query()->whereIn('cod_usuario', (clone $entities)->where('key', 'like', 'user.%')->select('legacy_id'))->get() as $user) {
                foreach ($menuIds as $menuId) {
                    $user->processes()->syncWithoutDetaching([$menuId => ['visualiza' => 1, 'cadastra' => 1, 'exclui' => 0]]);
                }
            }
            $attendanceField = Field::query()->firstOrCreate(['internal' => 'bc_attendance_mode'], [
                'field_type_id' => 3, 'group_type_id' => 1, 'name' => 'Como deseja entregar os documentos?',
                'description' => 'Online ou presencial. A escolha mantém o mesmo prazo documental.', 'required' => false,
            ]);
            $attendanceOptions = [];
            foreach (AttendanceMode::cases() as $mode) {
                $attendanceOptions[$mode->value] = $attendanceField->options()->firstOrCreate(['name' => $mode->value], ['weight' => 0]);
            }
            $processId = (clone $entities)->where('key', 'pmd.process')->value('legacy_id');
            if (!$processId) {
                $process = ProcessFactory::new()->withRequiredFields()->create([
                    'school_year_id' => 2026, 'name' => 'BC EDUCAR — Processo de Testes 2026',
                    'criteria' => 'Processo fictício para desenvolvimento e homologação. Sem validade.',
                    'message_footer' => 'AMBIENTE DE TESTES — SEM VALIDADE',
                    'active' => true, 'document_workflow_enabled' => true, 'reject_type_id' => 0, 'show_waiting_list' => true, 'waiting_list_limit' => 2,
                ]);
                BcDemoEntity::query()->create(['source' => $source, 'key' => 'pmd.process', 'legacy_id' => $process->id]);
                $processId = $process->id;

                $grades = (clone $entities)->where('key', 'like', 'grade.%')->pluck('legacy_id')->all();
                $process->grades()->sync($grades);
                $periods = LegacySchoolClass::query()->whereIn('cod_turma', (clone $entities)->where('key', 'like', 'class.%')->select('legacy_id'))
                    ->distinct()->pluck('turma_turno_id')->all();
                $process->periods()->sync($periods);
                foreach ([ProcessStage::TYPE_REGISTRATION, ProcessStage::TYPE_REGISTRATION_RENEWAL] as $type) {
                    $stage = ProcessStageFactory::new()->create([
                        'process_id' => $processId, 'process_stage_type_id' => $type, 'allow_waiting_list' => true,
                        'name' => $type === 1 ? 'Rematrícula Teste BC' : 'Matrícula Teste BC',
                        'start_at' => Carbon::parse(config('bc-demo.reference_date'))->subDays(60),
                        'end_at' => Carbon::parse(config('bc-demo.reference_date'))->addDays(60),
                    ]);
                    BcDemoEntity::query()->create(['source' => $source, 'key' => 'pmd.stage.' . $type, 'legacy_id' => $stage->id]);
                }
            }
            foreach (RegistrationRequest::query()->where('seed_source', $source)->get() as $request) {
                if ($request->pmd_preregistration_id) {
                    continue;
                }
                $student = $this->person($request->student->individual);
                $guardian = $this->person($request->guardian);
                $stageType = $request->kind === 'REMATRICULA' ? 1 : 2;
                $pmd = PreRegistrationFactory::new()->create([
                    'status' => PreRegistration::STATUS_SUMMONED,
                    'process_id' => $processId,
                    'process_stage_id' => (clone $entities)->where('key', 'pmd.stage.' . $stageType)->value('legacy_id'),
                    'preregistration_type_id' => $stageType, 'period_id' => $request->schoolClass->turma_turno_id,
                    'school_id' => $request->school_id, 'grade_id' => $request->grade_id,
                    'student_id' => $student->id, 'responsible_id' => $guardian->id,
                    'relation_type_id' => match ($request->student->tipo_responsavel) {
                        'm' => PreRegistration::RELATION_MOTHER, 'p' => PreRegistration::RELATION_FATHER,
                        default => PreRegistration::RELATION_GUARDIAN,
                    },
                    'protocol' => $request->protocol, 'code' => hash('sha256', 'BC-TEST-' . $request->protocol),
                    'external_person_id' => $request->student->ref_idpes,
                    'observation' => $source . ' | Atendimento: ' . $request->attendance_mode->value . ' | Documentação: /bc/matriculas/' . $request->id,
                    'created_at' => $request->created_at, 'updated_at' => $request->updated_at,
                ]);
                $request->update(['pmd_preregistration_id' => $pmd->id]);
                $pmd->fields()->create(['field_id' => $attendanceField->id, 'value' => (string) $attendanceOptions[$request->attendance_mode->value]->id]);
                app(PmdBridge::class)->sync($request);
            }
        });
    }

    private function person(LegacyIndividual $individual): Person
    {
        // PMD owns its intake snapshot. Link it to the existing legacy person; never create another legacy student.
        $person = Person::query()->firstOrCreate(['external_person_id' => $individual->getKey()], [
            'name' => $individual->person->nome, 'date_of_birth' => $individual->data_nasc,
            'gender' => $individual->sexo === 'M' ? Person::GENDER_MALE : Person::GENDER_FEMALE,
            'email' => $individual->person->email, 'cpf' => null, 'rg' => null, 'phone' => null, 'mobile' => null,
        ]);
        $place = PersonHasPlace::query()->where('person_id', $individual->getKey())->first()?->place;
        if ($place) {
            PersonAddress::query()->firstOrCreate(['person_id' => $person->id], [
                'address' => $place->address, 'number' => $place->number, 'neighborhood' => $place->neighborhood,
                'city' => 'Balneário Camboriú', 'postal_code' => '00000000',
                'latitude' => -26.99, 'longitude' => -48.63, 'manual_change_location' => true,
            ]);
        }

        return $person;
    }
}

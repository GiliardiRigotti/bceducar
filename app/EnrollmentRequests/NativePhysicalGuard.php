<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyRegistration;
use App\Models\LegacySchoolClass;
use App\Models\RegistrationRequest;
use App\Models\RegistrationStatus;
use Illuminate\Validation\ValidationException;

class NativePhysicalGuard
{
    public function assertCanEnroll(LegacyRegistration $registration, LegacySchoolClass $class): void
    {
        $request = RegistrationRequest::query()->where('workflow_version', 2)
            ->where('intermediate_registration_id', $registration->getKey())->first();
        if (!$request) {
            return;
        }
        if ($registration->aprovado !== RegistrationStatus::PRE_REGISTRATION) {
            $this->assertConfirmed($registration);

            return;
        }
        if (!$registration->ativo || !$class->ativo || $request->integration_status !== 'INTEGRATED'
            || !in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])
            || !$request->approved_at || $request->school_class_id != $class->getKey()
            || !$request->attendance_mode
            || $request->school_id != $class->school_id || $request->grade_id != $class->grade_id
            || (int) $request->school_year !== (int) $class->ano
            || $registration->ref_cod_aluno != $request->student_id
            || $registration->ref_ref_cod_escola != $class->school_id
            || $registration->ref_ref_cod_serie != $class->grade_id
            || (int) $registration->ano !== (int) $class->ano
            || (int) $registration->turno_pre_matricula !== (int) $class->turma_turno_id
            || $registration->activeEnrollments()->exists()) {
            throw ValidationException::withMessages(['physical' => 'Enturmação intermediária exige aprovação documental e vínculo compatível, sem outra enturmação ativa.']);
        }
        app(DeclaredStudentData::class)->assertApproved($request);
        $documents = $request->documents()->where('required', true)->where('status', '!=', DocumentStatus::Replaced->value)->get();
        if (!$documents->count() || $documents->contains(fn ($document) => $document->status !== DocumentStatus::Approved)) {
            throw ValidationException::withMessages(['physical' => 'Todos os documentos obrigatórios precisam estar aprovados antes da enturmação.']);
        }
        if ($class->getTotalEnrolled() >= $class->max_aluno) {
            throw ValidationException::withMessages(['physical' => 'Turma lotada. Não foi possível reservar a vaga.']);
        }
    }

    public function assertConfirmed(LegacyRegistration $registration): void
    {
        $request = RegistrationRequest::query()->where('workflow_version', 2)
            ->where('intermediate_registration_id', $registration->getKey())->first();
        if ($request && (!$request->physical_confirmed_at || !$request->physical_confirmed_by
            || !in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered]))) {
            throw ValidationException::withMessages(['physical' => 'Conclua a conferência física na Triagem Documental antes de promover esta pré-matrícula.']);
        }
    }
}

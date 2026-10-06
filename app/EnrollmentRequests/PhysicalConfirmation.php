<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use App\Models\LegacySchoolClass;
use App\Models\LegacyStudent;
use App\Models\RegistrationRequest;
use App\Models\RegistrationStatus;
use App\Services\EnrollmentService;
use App\Services\RegistrationService;
use App\User;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Services\EnrollmentService as PmdEnrollmentService;
use iEducar\Packages\PreMatricula\Services\RegistrationTransferService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhysicalConfirmation
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['physical' => $message]);
    }

    private function locked(RegistrationRequest $request, User $actor): RegistrationRequest
    {
        $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
        app(RequestAccess::class)->authorize($actor, $request);
        if ($request->workflow_version !== 2 || !$request->pmd_preregistration_id) {
            $this->fail('Esta solicitação mantém o fluxo anterior.');
        }

        return $request;
    }

    private function approved(RegistrationRequest $request, bool $integration = false): PreRegistration
    {
        if (!in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed]) || !$request->approved_at) {
            $this->fail('Aprovação digital necessária antes da conferência física.');
        }
        $pmd = PreRegistration::query()->findOrFail($request->pmd_preregistration_id);
        if (!in_array($pmd->status, $integration ? [PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION] : [PreRegistration::STATUS_IN_CONFIRMATION]) || !$request->attendance_mode) {
            $this->fail('A pré-matrícula deve estar em confirmação e possuir modalidade escolhida.');
        }
        app(DeclaredStudentData::class)->assertApproved($request);
        $documents = $request->documents()->where('status', '!=', DocumentStatus::Replaced->value)->get();
        if (!$documents->contains('required', true) || $documents->where('required', true)->contains(fn ($document) => $document->status !== DocumentStatus::Approved)) {
            $this->fail('Todos os documentos obrigatórios precisam estar aprovados.');
        }

        return $pmd;
    }

    private function classroom(RegistrationRequest $request, PreRegistration $pmd): LegacySchoolClass
    {
        $class = LegacySchoolClass::query()->lockForUpdate()->findOrFail($request->school_class_id);
        if ($class->school_id !== $request->school_id || $class->grade_id !== $request->grade_id
            || (int) $class->ano !== (int) $request->school_year || !$class->ativo
            || $pmd->school_id !== $request->school_id || $pmd->grade_id !== $request->grade_id
            || $pmd->period_id != $class->turma_turno_id || $pmd->process->school_year_id != $request->school_year) {
            $this->fail('Escola, série, turma, turno ou ano divergentes.');
        }

        return $class;
    }

    public function integrate(RegistrationRequest $request, User $actor): bool
    {
        // Authorization is outside the error handler: forbidden actions never become retryable failures.
        app(RequestAccess::class)->authorize($actor, $request);
        try {
            return DB::transaction(function () use ($request, $actor) {
                $request = $this->locked($request, $actor);
                $pmd = $this->approved($request, integration: true);
                $class = $this->classroom($request, $pmd);
                if ($request->intermediate_registration_id) {
                    $this->nativeRegistration($request);
                    app(PmdBridge::class)->sync($request);

                    return true;
                }
                app(NativeStudentConsolidation::class)->consolidate($request, $actor);
                $student = LegacyStudent::query()->lockForUpdate()->findOrFail($request->student_id);
                $candidates = LegacyRegistration::query()->where('ref_cod_aluno', $student->getKey())
                    ->where('ano', $request->school_year)->where('ativo', 1)->where('aprovado', RegistrationStatus::PRE_REGISTRATION)->lockForUpdate()->get();
                $registration = $candidates->first();
                if ($candidates->count() > 1 || ($registration && (
                    $registration->ref_ref_cod_escola !== $request->school_id || $registration->ref_ref_cod_serie !== $request->grade_id
                    || $registration->activeEnrollments()->exists()
                    || RegistrationRequest::query()->where('intermediate_registration_id', $registration->getKey())->exists()
                ))) {
                    $this->fail('Pré-matrícula nativa existente requer conferência do vínculo antes de reprocessar.');
                }
                if (!$registration) {
                    $service = new PmdEnrollmentService(new RegistrationTransferService, new EnrollmentService($actor));
                    $date = max(now()->startOfDay(), $class->begin_academic_year);
                    $registration = $service->preRegister($student, $pmd, $date);
                }
                if ($registration->turno_pre_matricula && (int) $registration->turno_pre_matricula !== (int) $class->turma_turno_id) {
                    $this->fail('O turno da pré-matrícula nativa diverge da turma selecionada.');
                }
                if (!$registration->turno_pre_matricula) {
                    $registration->update(['turno_pre_matricula' => $class->turma_turno_id]);
                }
                if ($registration->wasRecentlyCreated) {
                    $registration->update(['ref_usuario_cad' => $actor->getKey(), 'ultima_matricula' => 0,
                        'observacao' => 'Pré-matrícula em confirmação. Protocolo ' . $request->source_reference]);
                }
                $request->update(['intermediate_registration_id' => $registration->getKey(),
                    'integration_status' => 'INTEGRATED', 'integrated_at' => now(),
                    'physical_deadline' => now()->addDays($pmd->process->physical_delivery_days ?? 7)->endOfDay()]);
                app(PmdBridge::class)->sync($request);
                app(RegistrationWorkflow::class)->event($request, EventType::NativeIntegrated, $actor,
                    metadata: ['registration_id' => $registration->getKey(), 'native_status' => RegistrationStatus::PRE_REGISTRATION,
                        'delivery_deadline' => $request->physical_deadline->toIso8601String()]);

                return true;
            });
        } catch (\Throwable $error) {
            DB::transaction(function () use ($request, $actor, $error) {
                $request = $this->locked($request, $actor);
                if (!$request->intermediate_registration_id && !$request->status->terminal()) {
                    $request->update(['integration_status' => 'ERROR']);
                    app(RegistrationWorkflow::class)->event($request, EventType::IntegrationFailed, $actor,
                        metadata: ['error_class' => $error::class,
                            'error_code' => $error instanceof ValidationException && array_key_exists('identity', $error->errors()) ? 'IDENTITY_REVIEW_REQUIRED' : 'CONFIGURATION_OR_NATIVE_RULE',
                            'retryable' => true]);
                }
            });

            return false;
        }
    }

    private function nativeRegistration(RegistrationRequest $request): LegacyRegistration
    {
        if (!$request->intermediate_registration_id || $request->integration_status !== 'INTEGRATED') {
            $this->fail('Integração pendente. Confira o cadastro e reprocessse a aprovação digital antes de confirmar.');
        }
        $registration = LegacyRegistration::query()->lockForUpdate()->findOrFail($request->intermediate_registration_id);
        if ($registration->ref_cod_aluno !== $request->student_id || $registration->ref_ref_cod_escola !== $request->school_id
            || $registration->ref_ref_cod_serie !== $request->grade_id || (int) $registration->ano !== (int) $request->school_year
            || (int) $registration->turno_pre_matricula !== (int) $request->schoolClass->turma_turno_id
            || !$registration->ativo || $registration->aprovado !== RegistrationStatus::PRE_REGISTRATION
            || $registration->activeEnrollments()->exists()) {
            $this->fail('O vínculo nativo intermediário diverge da solicitação ou já possui enturmação.');
        }

        return $registration;
    }

    public function review(RegistrationRequest $request, User $actor, string $subject, bool $approved, ?string $reason): void
    {
        DB::transaction(function () use ($request, $actor, $subject, $approved, $reason) {
            $request = $this->locked($request, $actor);
            $renewingDataCorrection = !$approved && $subject === 'cadastro'
                && !$request->status->terminal() && $request->integration_status === 'INTEGRATED'
                && app(DeclaredStudentData::class)->reviewFor($request->pmd_preregistration_id)?->status === 'CORRECTION';
            $pmd = $renewingDataCorrection ? $request->preregistration : $this->approved($request);
            if ($pmd->status !== PreRegistration::STATUS_IN_CONFIRMATION) {
                $this->fail('A pré-matrícula precisa estar em confirmação para regularizar a conferência física.');
            }
            $this->nativeRegistration($request);
            $document = str_starts_with($subject, 'document:') ? $request->documents()->whereKey(substr($subject, 9))
                ->where('status', DocumentStatus::Approved->value)->first() : null;
            if (!$document && $subject !== 'cadastro') {
                $this->fail('Selecione um documento aprovado desta solicitação ou a ficha cadastral.');
            }
            if (!$approved && !trim($reason ?? '')) {
                $this->fail('Informe a divergência e o que deve ser regularizado.');
            }
            $before = DB::table('bc_physical_reviews')->where('registration_request_id', $request->id)->where('subject', $subject)->first();
            $deadline = $before?->status === 'PENDING' && $before->deadline ? Carbon::parse($before->deadline) : $request->physical_deadline;
            if ($approved && (!$deadline || $deadline->lt(now()))) {
                $this->fail('Prazo físico vencido. Registre uma divergência para conceder prazo de regularização motivado.');
            }
            $retry = $approved ? null : now()->addDays($pmd->process->physical_retry_days ?? 3)->endOfDay();
            DB::table('bc_physical_reviews')->updateOrInsert(['registration_request_id' => $request->id, 'subject' => $subject], [
                'status' => $approved ? 'APPROVED' : 'PENDING', 'reason' => $reason, 'deadline' => $retry,
                'reviewed_by' => $actor->getKey(), 'created_at' => $before?->created_at ?? now(), 'updated_at' => now(),
            ]);
            if (!$approved && $subject === 'cadastro') {
                app(DeclaredStudentData::class)->review($request, $actor, false, $reason);
            }
            app(RegistrationWorkflow::class)->event($request, $approved ? EventType::PhysicalReviewed : EventType::PhysicalPending,
                $actor, $document, metadata: ['subject' => $subject, 'decision' => $approved ? 'APPROVED' : 'PENDING',
                    'previous_decision' => $before?->status, 'reason' => $reason, 'delivery_deadline' => $retry?->toIso8601String()]);
        });
    }

    public function confirm(RegistrationRequest $request, User $actor): LegacyRegistration
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = $this->locked($request, $actor);
            if ($request->status === RequestStatus::Registered && $request->registration_id) {
                return $request->registration;
            }
            $pmd = $this->approved($request);
            $class = $this->classroom($request, $pmd);
            $registration = $this->nativeRegistration($request);
            $reviews = DB::table('bc_physical_reviews')->where('registration_request_id', $request->id)->get()->keyBy('subject');
            $subjects = $request->documents()->where('required', true)->where('status', DocumentStatus::Approved->value)
                ->pluck('id')->map(fn ($id) => 'document:' . $id)->push('cadastro');
            if ($reviews->contains('status', 'PENDING') || $subjects->contains(fn ($subject) => ($reviews->get($subject)?->status) !== 'APPROVED')) {
                $this->fail('Confira todos os documentos físicos obrigatórios e a ficha cadastral; regularize as divergências antes de confirmar.');
            }
            LegacyStudent::query()->lockForUpdate()->findOrFail($request->student_id);
            $previous = LegacyRegistration::query()->where('ref_cod_aluno', $request->student_id)->where('ano', $request->school_year)
                ->where('ativo', 1)->whereIn('aprovado', [1, 2, 3])->lockForUpdate()->get();
            if ($previous->isNotEmpty()) {
                // Existing movements retain the native PMD flow; no automatic transfer of an official enrollment.
                $this->fail('Existe matrícula ativa neste ano. Resolva o movimento no fluxo nativo antes da confirmação física.');
            }
            if (!$request->student->inepNumber && $request->grade->exigir_inep) {
                $this->fail('A série exige o código INEP do aluno. Regularize no cadastro nativo.');
            }
            if (LegacyEnrollment::query()->where('ref_cod_turma', $class->getKey())->where('ativo', 1)->count() >= $class->max_aluno) {
                $this->fail('Turma lotada. A pré-matrícula permanece em confirmação.');
            }
            $date = max(now()->startOfDay(), $class->begin_academic_year);
            // Persist inside this transaction only after every physical/native check; rollback on any failure.
            $request->update(['physical_confirmed_at' => now(), 'physical_confirmed_by' => $actor->getKey()]);
            (new RegistrationService($actor))->updateStatus($registration, ['nova_situacao' => RegistrationStatus::ONGOING]);
            try {
                (new EnrollmentService($actor))->enroll($registration, $class, $date);
            } catch (\Throwable $error) {
                if (str_starts_with($error::class, 'App\\Exceptions\\Enrollment\\')) {
                    $this->fail('A regra nativa impediu a enturmação. Confira vagas, calendário e vínculos do aluno antes de confirmar.');
                }
                throw $error;
            }
            LegacyRegistration::query()->where('ref_cod_aluno', $request->student_id)->whereKeyNot($registration->getKey())->update(['ultima_matricula' => 0]);
            $registration->update(['ultima_matricula' => 1]);
            $request->update(['status' => RequestStatus::Registered, 'registration_id' => $registration->getKey(),
                'physical_confirmed_at' => now(), 'physical_confirmed_by' => $actor->getKey(), 'updated_by' => $actor->getKey()]);
            $workflow = app(RegistrationWorkflow::class);
            $workflow->event($request, EventType::PhysicalConfirmed, $actor);
            $workflow->event($request, EventType::Registered, $actor, metadata: ['registration_id' => $registration->getKey(), 'movement' => 'ENROLLMENT']);
            $workflow->event($request, EventType::Enrolled, $actor, metadata: ['school_class_id' => $class->getKey()]);
            app(PmdBridge::class)->sync($request);

            return $registration;
        });
    }
}

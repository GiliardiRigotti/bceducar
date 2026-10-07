<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use App\Models\LegacySchoolClass;
use App\Models\LegacyStudent;
use App\Models\RegistrationDocument;
use App\Models\RegistrationRequest;
use App\Models\RegistrationStatus;
use App\Services\EnrollmentService;
use App\Services\RegistrationService;
use App\User;
use iEducar\Packages\PreMatricula\Models\Classroom as PmdClassroom;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Services\EnrollmentService as PmdEnrollmentService;
use iEducar\Packages\PreMatricula\Services\RegistrationTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationWorkflow
{
    public function __construct(private RequestAccess $access) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['workflow' => $message]);
    }

    private function locked(RegistrationRequest $request, User $actor): RegistrationRequest
    {
        $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
        $this->access->authorize($actor, $request);

        return $request;
    }

    private function editable(RegistrationRequest $request, bool $delivery = true): void
    {
        if ($request->status->terminal() || in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])) {
            $this->fail('A solicitação não aceita alterações documentais neste estado.');
        }
        if ($delivery && $request->document_deadline->lt(now())) {
            $this->fail('O prazo documental terminou, inclusive para atendimento presencial.');
        }
    }

    public function event(RegistrationRequest $request, EventType $event, ?User $actor = null, ?RegistrationDocument $document = null, array $metadata = [], ?string $actorType = null): void
    {
        $record = $request->events()->create([
            'event' => $event, 'actor_id' => $actor?->getKey(),
            'actor_type' => $actorType ?? ($actor ? 'OPERATOR' : 'SYSTEM'),
            'document_id' => $document?->id, 'metadata' => $metadata, 'created_at' => now(),
        ]);
        $kind = match ($event) {
            EventType::PreRegistrationApproved => 'DOCUMENTS_OPEN',
            EventType::ModeSelected, EventType::ModeChanged => 'MODE_SELECTED',
            EventType::Uploaded, EventType::Received => 'DOCUMENT_RECEIVED',
            EventType::Approved => $request->workflow_version === 2 ? null : 'DOCUMENTATION_APPROVED',
            EventType::NativeIntegrated => 'PHYSICAL_REQUIRED',
            EventType::PhysicalReminder => 'PHYSICAL_REMINDER',
            EventType::PhysicalExpired => 'PHYSICAL_EXPIRED',
            EventType::PhysicalPending => 'CORRECTION',
            EventType::CorrectionRequested, EventType::DocumentRejected => 'CORRECTION',
            EventType::Expired => 'EXPIRED',
            EventType::DeadlineReminder => 'REMINDER',
            EventType::Registered => 'REGISTERED',
            default => null,
        };
        if ($kind && $request->pmd_preregistration_id && !$request->seed_source
            && !($event === EventType::Uploaded && ($metadata['source'] ?? null) === 'PMD')) {
            DB::table('bc_guardian_notifications')->insertOrIgnore([
                'registration_event_id' => $record->id, 'kind' => $kind,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function receive(RegistrationRequest $request, User $actor, DocumentType|string $type, string $path, ?RegistrationDocument $previous = null, array $metadata = []): RegistrationDocument
    {
        return $this->receiveDocument($request, $actor, null, $type, $path, $previous, $metadata);
    }

    public function receiveFromGuardian(RegistrationRequest $request, int $pmdId, DocumentType|string $type, string $path, ?RegistrationDocument $previous = null, array $metadata = []): RegistrationDocument
    {
        return $this->receiveDocument($request, null, $pmdId, $type, $path, $previous, $metadata);
    }

    public function receiveInPerson(RegistrationRequest $request, User $actor, array $types, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $actor, $types, $note) {
            $request = $this->locked($request, $actor);
            $this->editable($request, delivery: false);
            if ($request->attendance_mode !== AttendanceMode::InPerson) {
                $this->fail('Esta solicitação está configurada para entrega online.');
            }
            foreach (array_unique(array_map(fn ($type) => DocumentCode::value($type), $types)) as $type) {
                $previous = $request->documents()->where('document_type', DocumentCode::value($type))
                    ->whereIn('status', [DocumentStatus::Rejected->value, DocumentStatus::Correction->value])
                    ->latest('id')->first();
                $this->receiveDocument($request, $actor, null, $type, null, $previous, ['note' => $note]);
            }
        });
    }

    private function receiveDocument(RegistrationRequest $request, ?User $actor, ?int $pmdId, DocumentType|string $type, ?string $path, ?RegistrationDocument $previous, array $metadata): RegistrationDocument
    {
        return DB::transaction(function () use ($request, $actor, $pmdId, $type, $path, $previous, $metadata) {
            $request = $actor ? $this->locked($request, $actor) : RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($pmdId !== null) {
                abort_unless($request->pmd_preregistration_id === $pmdId, 403);
                if ($request->attendance_mode !== AttendanceMode::Online) {
                    $this->fail('Para enviar arquivos, escolha a entrega online.');
                }
            }
            $this->editable($request, delivery: false);
            $this->pmdOpen($request);
            $this->chosenMode($request);
            $existing = $request->documents()->where('document_type', DocumentCode::value($type))
                ->where('status', '!=', DocumentStatus::Replaced->value)->first();
            if (!$existing && !$previous) {
                $this->fail('Documento não previsto nesta solicitação.');
            }
            if ($previous) {
                $previous = $request->documents()->lockForUpdate()->findOrFail($previous->id);
                if ($previous->document_type->value !== DocumentCode::value($type) || !in_array($previous->status, [DocumentStatus::Rejected, DocumentStatus::Correction])) {
                    $this->fail('Só documentos rejeitados ou com correção solicitada podem ser substituídos.');
                }
                if (DocumentDeadlines::effective($previous, $request)->lt(now())) {
                    $this->fail('O prazo de reentrega deste documento terminou.');
                }
                $previous->update(['status' => DocumentStatus::Replaced]);
                $this->event($request, EventType::Replaced, $actor, $previous, actorType: $pmdId !== null ? 'GUARDIAN' : null);
            } elseif ($request->document_deadline->lt(now())) {
                $this->fail('O prazo total de entrega dos documentos terminou.');
            } elseif ($existing && $existing->status !== DocumentStatus::Pending) {
                $this->fail('Já existe documento deste tipo; solicite correção para substituí-lo.');
            }
            $attributes = [
                'document_type' => $type, 'document_name' => $previous?->document_name ?? $existing?->document_name, 'required' => $previous?->required ?? $existing?->required ?? in_array(DocumentType::tryFrom(DocumentCode::value($type)), DocumentType::required($request->kind)),
                'status' => DocumentStatus::Sent, 'path' => $path, 'received_at' => now(),
                'replaces_id' => $previous?->id, 'delivery_deadline' => $previous?->delivery_deadline,
                'original_filename' => $metadata['original_filename'] ?? null,
                'mime_type' => $metadata['mime_type'] ?? null,
                'file_size' => $metadata['file_size'] ?? null,
                'sha256' => $metadata['sha256'] ?? null,
            ];
            if ($existing && !$previous) {
                $existing->update($attributes);
                $document = $existing;
            } else {
                $document = $request->documents()->create($attributes);
            }
            $request->update(['status' => app(DocumentProgress::class)->status($request),
                'submitted_at' => $request->submitted_at ?? now(), 'updated_by' => $actor?->getKey()]);
            $this->event($request, $request->attendance_mode === AttendanceMode::InPerson ? EventType::Received : EventType::Uploaded, $actor, $document,
                metadata: array_filter(['note' => $metadata['note'] ?? null]),
                actorType: $pmdId !== null ? 'GUARDIAN' : null);
            app(PmdBridge::class)->syncDocumentation($request);

            return $document;
        });
    }

    public function changeGuardianMode(RegistrationRequest $request, int $pmdId, AttendanceMode $mode): void
    {
        DB::transaction(function () use ($request, $pmdId, $mode) {
            $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->pmd_preregistration_id === $pmdId, 403);
            $this->editable($request);
            $this->pmdOpen($request);
            if ($request->attendance_mode === $mode) {
                return;
            }
            $before = $request->attendance_mode?->value;
            $request->update(['attendance_mode' => $mode]);
            DB::table('preregistrations')->where('id', $pmdId)->update([
                'document_submission_method' => $mode === AttendanceMode::InPerson ? 'IN_PERSON' : 'ONLINE',
                'documentation_deadline' => $request->document_deadline,
            ]);
            $this->event($request, $before ? EventType::ModeChanged : EventType::ModeSelected, metadata: ['before' => $before, 'after' => $mode->value], actorType: 'GUARDIAN');
        });
    }

    public function review(RegistrationDocument $document, User $actor, DocumentStatus $status, ?string $reason = null): void
    {
        DB::transaction(function () use ($document, $actor, $status, $reason) {
            $request = $this->locked($document->request, $actor);
            $this->editable($request, delivery: false);
            $this->pmdOpen($request);
            $this->chosenMode($request);
            $document = $request->documents()->lockForUpdate()->findOrFail($document->id);
            if (!in_array($document->status, [DocumentStatus::Sent, DocumentStatus::UnderReview]) || !$document->received_at
                || !DocumentDeadlines::timely($document, $request)) {
                $this->fail('Documento não recebido ou já analisado.');
            }
            $event = match ($status) {
                DocumentStatus::UnderReview => EventType::ReviewStarted,
                DocumentStatus::Approved => EventType::DocumentApproved,
                DocumentStatus::Rejected => EventType::DocumentRejected,
                DocumentStatus::Correction => EventType::CorrectionRequested,
                default => $this->fail('Resultado de análise inválido.'),
            };
            if (in_array($status, [DocumentStatus::Rejected, DocumentStatus::Correction]) && !trim($reason ?? '')) {
                $this->fail('Informe o motivo da pendência.');
            }
            $document->update(['delivery_deadline' => in_array($status, [DocumentStatus::Rejected, DocumentStatus::Correction]) ? DocumentDeadlines::retry($request) : $document->delivery_deadline, 'status' => $status, 'reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $actor->getKey()]);
            $request->update(['status' => app(DocumentProgress::class)->status($request),
                'updated_by' => $actor->getKey()]);
            $this->event($request, $event, $actor, $document, metadata: ['delivery_deadline' => $document->delivery_deadline?->toDateTimeString()]);
            app(PmdBridge::class)->syncDocumentation($request);
        });
    }

    private function documentsApproved(RegistrationRequest $request): void
    {
        $requiredTypes = $request->documents()->where('required', true)
            ->where('status', '!=', DocumentStatus::Replaced->value)->distinct()->pluck('document_type');
        foreach ($requiredTypes as $type) {
            if (!$request->documents()->where('document_type', DocumentCode::value($type))
                ->where('status', DocumentStatus::Approved->value)
                ->whereNotNull('received_at')
                ->whereRaw('received_at <= COALESCE(delivery_deadline, ?)', [$request->document_deadline])->exists()) {
                $this->fail('Todos os documentos obrigatórios devem ser recebidos no prazo e aprovados.');
            }
        }
        if ($request->documents()->where('required', true)
            ->whereNotIn('status', [DocumentStatus::Approved->value, DocumentStatus::Replaced->value])->exists()) {
            $this->fail('Há documentos obrigatórios pendentes.');
        }
    }

    private function pmdOpen(RegistrationRequest $request): void
    {
        if (!$request->pmd_preregistration_id) {
            return;
        }
        $status = PreRegistration::query()->findOrFail($request->pmd_preregistration_id)->status;
        if (!in_array($status, [PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])) {
            $this->fail('A documentação exige pré-matrícula deferida pela escola.');
        }
    }

    private function chosenMode(RegistrationRequest $request): void
    {
        if (!$request->attendance_mode) {
            $this->fail('O responsável deve escolher a forma de entrega após o deferimento.');
        }
    }

    public function approveAndEnroll(RegistrationRequest $request, User $actor): LegacyRegistration
    {
        if ($request->fresh()->workflow_version === 2) {
            $this->fail('No fluxo atual, aprove digitalmente e realize a conferência física em ações separadas.');
        }

        return DB::transaction(function () use ($request, $actor) {
            $request = $this->locked($request, $actor);
            if ($request->status !== RequestStatus::Registered) {
                $this->approve($request, $actor);
            }

            return $this->finalize($request->fresh(), $actor);
        });
    }

    public function approve(RegistrationRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            $request = $this->locked($request, $actor);
            $this->pmdOpen($request);
            $this->chosenMode($request);
            if (in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered])) {
                return;
            }
            $this->editable($request, delivery: false);
            $this->pmdOpen($request);
            $this->chosenMode($request);
            $this->documentsApproved($request);
            app(DeclaredStudentData::class)->assertApproved($request);
            $request->update(['status' => RequestStatus::Approved, 'approved_at' => now(), 'updated_by' => $actor->getKey()]);
            $this->event($request, EventType::Approved, $actor);
            app(PmdBridge::class)->sync($request);
        });
        if ($request->fresh()->workflow_version === 2 && $request->fresh()->status !== RequestStatus::Registered) {
            app(PhysicalConfirmation::class)->integrate($request->fresh(), $actor);
        }
    }

    public function finalize(RegistrationRequest $request, User $actor): LegacyRegistration
    {
        if ($request->workflow_version === 2) {
            return app(PhysicalConfirmation::class)->confirm($request, $actor);
        }

        return DB::transaction(function () use ($request, $actor) {
            $request = $this->locked($request, $actor);
            if ($request->status === RequestStatus::Registered && $request->registration_id) {
                return $request->registration;
            }
            if (!in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed]) || !$request->approved_at) {
                $this->fail('Na seção Decisão, selecione Aprovar documentação e confirme antes de efetivar a matrícula. Os documentos obrigatórios precisam estar aprovados.');
            }
            $this->pmdOpen($request);
            $this->chosenMode($request);
            $this->documentsApproved($request);
            app(DeclaredStudentData::class)->assertApproved($request);
            // Serialize competing requests for the same student, and competing allocations to the same class.
            $class = LegacySchoolClass::query()->lockForUpdate()->findOrFail($request->school_class_id);
            if ($class->school_id !== $request->school_id || $class->grade_id !== $request->grade_id
                || (int) $class->ano !== $request->school_year || !$class->ativo) {
                $this->fail('Turma incompatível com escola, série ou ano letivo.');
            }
            if (LegacyEnrollment::query()->where('ref_cod_turma', $class->getKey())->where('ativo', 1)->count() >= $class->max_aluno) {
                $this->fail('Turma lotada. Nenhuma matrícula foi criada.');
            }
            app(NativeStudentConsolidation::class)->consolidate($request, $actor);
            LegacyStudent::query()->lockForUpdate()->findOrFail($request->student_id);
            $activeRegistrations = LegacyRegistration::query()->where('ref_cod_aluno', $request->student_id)
                ->where('ano', $request->school_year)->where('ativo', 1)->whereIn('aprovado', [1, 2, 3])
                ->lockForUpdate()->get();
            $previousRegistration = $activeRegistrations->first();
            if ($activeRegistrations->isNotEmpty()) {
                $nativeMovementAllowed = $request->pmd_preregistration_id
                    && in_array(config('prematricula.features.allow_transfer_registration'), [true, 1, '1'], true)
                    && $activeRegistrations->count() === 1
                    && $previousRegistration->ref_ref_cod_serie === $request->grade_id
                    && $previousRegistration->aprovado === \App_Model_MatriculaSituacao::EM_ANDAMENTO;
                if (!$nativeMovementAllowed) {
                    $this->fail('O aluno já possui matrícula ativa neste ano; transferência/remanejamento exige configuração nativa habilitada e uma única matrícula em andamento na mesma série.');
                }
                $this->access->authorizeSchool($actor, $previousRegistration->ref_ref_cod_escola);
            }
            $movement = !$previousRegistration ? 'ENROLLMENT'
                : ($previousRegistration->ref_ref_cod_escola === $request->school_id ? 'RELOCATION' : 'TRANSFER');
            if ($request->pmd_preregistration_id) {
                $pmd = PreRegistration::query()->findOrFail($request->pmd_preregistration_id);
                if ($pmd->school_id !== $request->school_id || $pmd->grade_id !== $request->grade_id
                    || $pmd->period_id != $class->turma_turno_id || $pmd->process->school_year_id != $request->school_year) {
                    $this->fail('A inscrição PMD diverge da escola ou série da solicitação documental.');
                }
                $pmd->external_person_id = $request->student->ref_idpes;
                $pmd->saveOrFail();
                $pmdClass = PmdClassroom::query()->findOrFail($class->getKey());
                $service = new PmdEnrollmentService(new RegistrationTransferService, new EnrollmentService($actor));
                $registration = $service->enroll($pmd, $pmdClass);
                if ($registration->ref_cod_aluno !== $request->student_id) {
                    $this->fail('O serviço PMD retornou outro aluno; a transação foi cancelada.');
                }
                LegacyRegistration::query()->where('ref_cod_aluno', $request->student_id)
                    ->whereKeyNot($registration->getKey())->update(['ultima_matricula' => 0]);
                $registration->update(['ref_usuario_cad' => $actor->getKey(), 'ultima_matricula' => 1,
                    'observacao' => 'Solicitação ' . $request->protocol]);
                LegacyEnrollment::query()->where('ref_cod_matricula', $registration->getKey())
                    ->where('ref_cod_turma', $class->getKey())->where('ativo', 1)
                    ->update(['ref_usuario_cad' => $actor->getKey()]);
            } else {
                LegacyRegistration::query()->where('ref_cod_aluno', $request->student_id)->update(['ultima_matricula' => 0]);
                $registration = LegacyRegistration::query()->create([
                    'ref_cod_aluno' => $request->student_id, 'ref_ref_cod_escola' => $request->school_id,
                    'ref_ref_cod_serie' => $request->grade_id, 'ref_cod_curso' => $class->course_id,
                    'ano' => $request->school_year, 'ref_usuario_cad' => $actor->getKey(),
                    'aprovado' => \App_Model_MatriculaSituacao::EM_ANDAMENTO, 'ativo' => 1,
                    'ultima_matricula' => 1, 'data_matricula' => now()->toDateString(),
                    'observacao' => 'Solicitação ' . $request->protocol,
                ]);
                $enrollment = new LegacyEnrollment;
                $enrollment->forceFill([
                    'ref_cod_matricula' => $registration->getKey(), 'ref_cod_turma' => $class->getKey(),
                    'sequencial' => 1, 'ref_usuario_cad' => $actor->getKey(), 'turno_id' => $class->turma_turno_id,
                    'ativo' => 1, 'data_enturmacao' => now()->toDateString(),
                ])->save();
            }
            $request->update(['status' => RequestStatus::Registered, 'registration_id' => $registration->getKey(), 'updated_by' => $actor->getKey()]);
            $this->event($request, EventType::Registered, $actor, metadata: ['registration_id' => $registration->getKey(),
                'movement' => $movement, 'previous_registration_id' => $previousRegistration?->getKey()]);
            $this->event($request, EventType::Enrolled, $actor, metadata: ['school_class_id' => $class->getKey()]);
            app(PmdBridge::class)->sync($request);

            return $registration;
        });
    }

    public function expire(RegistrationRequest $request): bool
    {
        return DB::transaction(function () use ($request) {
            $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status->terminal() || in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])
                || $request->document_deadline->gte(now())) {
                return false;
            }
            $required = $request->documents()->where('required', true)
                ->where('status', '!=', DocumentStatus::Replaced->value)->get();
            if ($required->contains(fn ($document) => in_array($document->status, [DocumentStatus::Rejected, DocumentStatus::Correction])
                && $document->delivery_deadline && $document->delivery_deadline->gte(now()))) {
                return false;
            }
            if ($required->isNotEmpty() && $required->every(fn ($document) => in_array($document->status, [DocumentStatus::Sent, DocumentStatus::UnderReview, DocumentStatus::Approved])
                && DocumentDeadlines::timely($document, $request))) {
                // Delivery deadline does not end the school's review of documents delivered on time.
                return false;
            }
            $noShow = $request->attendance_mode === AttendanceMode::InPerson && !$request->documents()->whereNotNull('received_at')->exists();
            $request->update(['status' => $noShow ? RequestStatus::NoShow : RequestStatus::Expired]);
            $this->event($request, EventType::Expired, metadata: ['no_show' => $noShow]);
            app(PmdBridge::class)->sync($request);

            return true;
        });
    }

    public function close(RegistrationRequest $request, User $actor, bool $cancel, string $reason): void
    {
        DB::transaction(function () use ($request, $actor, $cancel, $reason) {
            $request = $this->locked($request, $actor);
            if (trim($reason) && $request->status === ($cancel ? RequestStatus::Cancelled : RequestStatus::Rejected)) {
                return;
            }
            if (($request->status->terminal() && !in_array($request->status, [RequestStatus::Expired, RequestStatus::NoShow]))
                || !trim($reason)) {
                $this->fail('Informe um motivo e utilize uma solicitação aberta.');
            }
            if ($request->workflow_version === 2 && $request->intermediate_registration_id) {
                $native = LegacyRegistration::query()->lockForUpdate()->findOrFail($request->intermediate_registration_id);
                if ($native->aprovado !== RegistrationStatus::PRE_REGISTRATION
                    || $native->ref_cod_aluno != $request->student_id
                    || $native->ref_ref_cod_escola != $request->school_id
                    || $native->ref_ref_cod_serie != $request->grade_id
                    || (int) $native->ano !== (int) $request->school_year
                    || $native->activeEnrollments()->where('ref_cod_turma', '!=', $request->school_class_id)->exists()) {
                    $this->fail('O vínculo nativo foi alterado. Confira a matrícula antes de encerrar.');
                }
                if ($native->ativo) {
                    (new RegistrationService($actor))->cancelRegistration($native);
                }
            }
            $before = $request->status->value;
            $request->update(['status' => $cancel ? RequestStatus::Cancelled : RequestStatus::Rejected,
                $cancel ? 'cancelled_at' : 'rejected_at' => now(), 'updated_by' => $actor->getKey()]);
            $this->event($request, $cancel ? EventType::Cancelled : EventType::Rejected, $actor,
                metadata: ['reason' => $reason, 'previous_status' => $before]);
            app(PmdBridge::class)->sync($request);
        });
    }
}

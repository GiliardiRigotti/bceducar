<?php

namespace App\EnrollmentRequests;

use App\Models\LegacySchoolClass;
use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\Events\PreRegistrationStatusUpdatedEvent;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmdIntake
{
    public function import(int $pmdId, int $classId, User $actor, ?AttendanceMode $mode = null): RegistrationRequest
    {
        return DB::transaction(function () use ($pmdId, $classId, $actor, $mode) {
            // Lock the physical row rather than the PMD builder's position view with window functions.
            DB::table('preregistrations')->where('id', $pmdId)->lockForUpdate()->firstOrFail();
            $pmd = PreRegistration::query()->findOrFail($pmdId);
            app(RequestAccess::class)->authorizeSchool($actor, $pmd->school_id);
            $existing = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->first();
            if ($existing) {
                return $existing;
            }
            if (!in_array($pmd->status, [PreRegistration::STATUS_WAITING, PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])) {
                throw ValidationException::withMessages(['pmd' => 'Somente inscrições abertas ou convocadas podem ser deferidas para a fase documental.']);
            }
            if (in_array($pmd->process->document_workflow_enabled, [false, 0, '0'], true)) {
                if (!$pmd->documentation_status) {
                    throw ValidationException::withMessages(['pmd' => 'Ative a documentação deste processo na configuração antes de liberar novas solicitações.']);
                }
            }
            $class = LegacySchoolClass::query()->findOrFail($classId);
            if ($class->school_id != $pmd->school_id || $class->grade_id != $pmd->grade_id
                || $class->turma_turno_id != $pmd->period_id || $class->ano != $pmd->process->school_year_id || !$class->ativo) {
                throw ValidationException::withMessages(['classroom' => 'Turma incompatível com a pré-matrícula.']);
            }
            // Preserve actual choices from an already released documentary stage, never intake form answers.
            $mode = $pmd->status === PreRegistration::STATUS_WAITING ? null : match ($pmd->document_submission_method) {
                'ONLINE' => AttendanceMode::Online,
                'IN_PERSON' => AttendanceMode::InPerson,
                default => null,
            };
            $deadline = DocumentDeadlines::initial($pmd);
            if ($deadline->lt(now()) && !app(PmdDocumentExpiration::class)
                ->hasCompleteTimelyDelivery($pmdId, $pmd->process_id, $deadline)) {
                throw ValidationException::withMessages(['documentation_deadline' => 'O prazo documental do processo já terminou.']);
            }
            $request = RegistrationRequest::query()->create([
                'workflow_version' => 2, 'integration_status' => 'PENDING',
                'protocol' => 'PMD-' . $pmd->id . '-' . $pmd->protocol, 'student_id' => null,
                'guardian_id' => null, 'school_id' => $pmd->school_id, 'grade_id' => $pmd->grade_id,
                'school_class_id' => $classId, 'school_year' => $pmd->process->school_year_id,
                'attendance_mode' => $mode, 'status' => RequestStatus::AwaitingDocuments,
                'kind' => $pmd->isRegistrationRenewal() ? 'REMATRICULA' : 'NOVA',
                'source' => 'PMD', 'source_reference' => $pmd->protocol, 'pmd_preregistration_id' => $pmdId,
                'document_deadline' => $deadline,
                'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey(),
            ]);
            app(DeclaredStudentData::class)->initialize($pmdId);
            // School approval releases documentation without completing enrollment.
            if ($pmd->status === PreRegistration::STATUS_WAITING) {
                $before = $pmd->status;
                $pmd->summon();
                $pmd->saveOrFail();
                event(new PreRegistrationStatusUpdatedEvent($pmd, $before, $pmd->status));
            }
            app(RegistrationWorkflow::class)->event($request, EventType::Created, $actor, metadata: ['pmd_preregistration_id' => $pmdId, 'preregistration_approved_for_documents' => true]);

            $configured = DB::table('process_document_types')->where('process_id', $pmd->process_id)->exists();
            $policy = DB::table('process_document_types as policy')
                ->join('preregistration_document_types as type', 'type.id', '=', 'policy.document_type_id')
                ->where('policy.process_id', $pmd->process_id)->where('type.active', true)
                ->whereNotNull('type.code')->get(['type.id', 'type.code', 'type.name', 'policy.required'])->keyBy('code');
            if ($configured && $policy->isEmpty()) {
                throw ValidationException::withMessages(['documents' => 'O processo não possui tipos documentais ativos.']);
            }
            $sourceDocuments = DB::table('preregistration_documents')->where('preregistration_id', $pmdId)
                ->orderBy('id')->get()->groupBy('document_type_id');
            $codes = $configured ? $policy->keys()->all() : array_column(DocumentType::cases(), 'value');
            foreach ($codes as $code) {
                $type = $code;
                if ($configured && !$policy->has($code)) {
                    continue;
                }
                $required = $configured ? (bool) $policy->get($code)->required : in_array(DocumentType::tryFrom($code), DocumentType::required($request->kind));
                $versions = $configured ? $sourceDocuments->get($policy->get($code)->id, collect()) : collect();
                if ($versions->isEmpty()) {
                    $request->documents()->create(['document_type' => $type, 'document_name' => $configured ? $policy->get($code)->name : null, 'status' => DocumentStatus::Pending,
                        'required' => $required]);

                    continue;
                }
                $mapped = [];
                foreach ($versions as $source) {
                    $document = $request->documents()->create([
                        'document_type' => $type, 'document_name' => $configured ? $policy->get($code)->name : null, 'required' => $required,
                        'status' => match ($source->status) {
                            'REPLACED' => DocumentStatus::Replaced,
                            'REJECTED' => DocumentStatus::Rejected,
                            'APPROVED' => DocumentStatus::Approved,
                            default => DocumentStatus::Sent,
                        },
                        'path' => $source->file_path, 'received_at' => $source->created_at,
                        'reviewed_at' => $source->reviewed_at, 'reviewed_by' => $source->reviewed_by,
                        'reason' => $source->rejection_reason,
                        'original_filename' => $source->original_filename,
                        'mime_type' => $source->mime_type, 'file_size' => $source->file_size,
                        'sha256' => $source->hash,
                        'replaces_id' => $mapped[$source->replaces_id] ?? null,
                    ]);
                    $mapped[$source->id] = $document->id;
                    app(RegistrationWorkflow::class)->event($request, EventType::Uploaded, document: $document,
                        metadata: ['source' => 'PMD', 'source_document_id' => $source->id], actorType: 'GUARDIAN');
                }
            }
            $requiredDocuments = $request->documents()->where('required', true)
                ->where('status', '!=', DocumentStatus::Replaced->value)->get();
            $complete = $requiredDocuments->every(fn ($document) => in_array($document->status,
                [DocumentStatus::Sent, DocumentStatus::Approved]) && $document->received_at?->lte($deadline));
            if ($sourceDocuments->isNotEmpty()) {
                $request->update(['status' => $complete ? RequestStatus::AwaitingReview : RequestStatus::AwaitingDocuments,
                    'submitted_at' => $sourceDocuments->flatten()->min('created_at')]);
            }
            DB::table('preregistrations')->where('id', $pmdId)->update([
                'document_submission_method' => $mode?->value ? ($mode === AttendanceMode::InPerson ? 'IN_PERSON' : 'ONLINE') : null,
                'documentation_status' => $sourceDocuments->isNotEmpty() && $complete ? 'AWAITING_REVIEW' : 'AWAITING_DOCUMENTS',
                'documentation_deadline' => $deadline,
            ]);

            app(RegistrationWorkflow::class)->event($request, EventType::PreRegistrationApproved, $actor,
                metadata: ['documentation_deadline' => $deadline->toIso8601String()]);
            app(RegistrationWorkflow::class)->event($request, EventType::DocumentsReleased, $actor,
                metadata: ['documentation_deadline' => $deadline->toIso8601String()]);

            return $request;
        });
    }
}

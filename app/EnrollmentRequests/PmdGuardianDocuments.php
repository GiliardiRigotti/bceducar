<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmdGuardianDocuments
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['documents' => $message]);
    }

    private function open(PreRegistration $pmd): void
    {
        if ($pmd->isWaitingList() || !in_array($pmd->status, [
            PreRegistration::STATUS_SUMMONED,
            PreRegistration::STATUS_IN_CONFIRMATION,
        ])) {
            $this->fail('A documentação será liberada somente após a aprovação da pré-matrícula pela escola.');
        }
    }

    private function locked(int $pmdId): PreRegistration
    {
        DB::table('preregistrations')->where('id', $pmdId)->lockForUpdate()->firstOrFail();
        $pmd = PreRegistration::query()->with('process')->findOrFail($pmdId);
        if (DB::table('bc_registration_requests')->where('pmd_preregistration_id', $pmdId)->exists()) {
            $this->fail('A solicitação já passou para a análise escolar. Reabra a página.');
        }
        $this->open($pmd);
        if (in_array($pmd->process->document_workflow_enabled, [false, 0, '0'], true) && !$pmd->documentation_status) {
            $this->fail('A etapa documental deste processo ainda não foi ativada pela administração.');
        }

        return $pmd;
    }

    private function deadline(PreRegistration $pmd): Carbon
    {
        $deadline = DocumentDeadlines::initial($pmd);
        if ($deadline->lt(now())) {
            $this->fail('O prazo documental terminou. Procure a escola.');
        }

        return $deadline;
    }

    private function event(int $pmdId, string $event, array $metadata = []): void
    {
        app(PmdDocumentAudit::class)->record($pmdId, $event, $metadata);
    }

    public function choose(int $pmdId, AttendanceMode $mode): void
    {
        DB::transaction(function () use ($pmdId, $mode) {
            $pmd = $this->locked($pmdId);
            $deadline = $this->deadline($pmd);
            $value = $mode === AttendanceMode::InPerson ? 'IN_PERSON' : 'ONLINE';
            $before = $pmd->document_submission_method;
            DB::table('preregistrations')->where('id', $pmdId)->update([
                'document_submission_method' => $value,
                'documentation_status' => $pmd->documentation_status ?: 'AWAITING_DOCUMENTS',
                'documentation_deadline' => $deadline,
            ]);
            if ($before !== $value) {
                $this->event($pmdId, $before ? 'DOCUMENT_MODE_CHANGED' : 'DOCUMENT_MODE_SELECTED',
                    ['before' => $before, 'after' => $value]);
            }
        });
    }

    public function upload(int $pmdId, string $code, string $path, array $metadata): void
    {
        DB::transaction(function () use ($pmdId, $code, $path, $metadata) {
            $pmd = $this->locked($pmdId);
            $deadline = $this->deadline($pmd);
            if ($pmd->document_submission_method !== 'ONLINE') {
                $this->fail('Escolha o envio online antes de anexar documentos.');
            }
            $type = DB::table('process_document_types as policy')
                ->join('preregistration_document_types as type', 'type.id', '=', 'policy.document_type_id')
                ->where('policy.process_id', $pmd->process_id)->where('type.active', true)
                ->where('type.code', $code)->select('type.id')->first();
            if (!$type) {
                $this->fail('Documento não solicitado neste processo.');
            }
            $previous = DB::table('preregistration_documents')
                ->where('preregistration_id', $pmdId)->where('document_type_id', $type->id)
                ->latest('id')->first();
            if ($previous && $previous->status !== 'REJECTED') {
                $this->fail('O documento já foi enviado. Aguarde a análise para reenviar.');
            }
            if ($previous) {
                DB::table('preregistration_documents')->where('id', $previous->id)->update([
                    'status' => 'REPLACED', 'updated_at' => now(),
                ]);
            }
            $id = DB::table('preregistration_documents')->insertGetId([
                'preregistration_id' => $pmdId, 'document_type_id' => $type->id,
                'file_path' => $path, 'original_filename' => $metadata['original_filename'],
                'mime_type' => $metadata['mime_type'], 'file_size' => $metadata['file_size'],
                'hash' => $metadata['sha256'], 'status' => 'PENDING',
                'replaces_id' => $previous?->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $required = DB::table('process_document_types as policy')
                ->where('policy.process_id', $pmd->process_id)->where('policy.required', true)
                ->pluck('policy.document_type_id');
            $complete = $required->every(fn ($typeId) => DB::table('preregistration_documents')
                ->where('preregistration_id', $pmdId)->where('document_type_id', $typeId)
                ->where('status', 'PENDING')->exists());
            DB::table('preregistrations')->where('id', $pmdId)->update([
                'documentation_status' => $complete ? 'AWAITING_REVIEW' : 'AWAITING_DOCUMENTS',
                'documentation_deadline' => $deadline,
            ]);
            $this->event($pmdId, $previous ? 'DOCUMENT_REPLACED' : 'DOCUMENT_UPLOADED',
                ['document_id' => $id, 'document_type' => $code, 'sha256' => $metadata['sha256']]);
        });
    }
}

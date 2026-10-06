<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PmdDocumentExpiration
{
    public function expire(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Share the intake/upload row lock so expiration cannot race with school linkage.
            $pmd = DB::table('preregistrations')->where('id', $id)->lockForUpdate()->first();
            if (!$pmd || DB::table('bc_registration_requests')->where('pmd_preregistration_id', $id)->exists()
                || !in_array($pmd->status, [PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])
                || !in_array($pmd->documentation_status, ['AWAITING_DOCUMENTS', 'AWAITING_REVIEW', 'UNDER_REVIEW', 'CORRECTION_REQUIRED'])) {
                return false;
            }
            $value = $pmd->documentation_deadline
                ?? DB::table('processes')->where('id', $pmd->process_id)->value('documentation_deadline');
            if (!$value || ($deadline = Carbon::parse($value))->gte(now())) {
                return false;
            }
            if ($this->hasCompleteTimelyDelivery($id, $pmd->process_id, $deadline)) {
                return false;
            }
            $hasDocuments = DB::table('preregistration_documents')->where('preregistration_id', $id)->exists();
            DB::table('preregistrations')->where('id', $id)->update([
                'documentation_status' => 'DEADLINE_EXPIRED', 'documentation_deadline' => $deadline,
            ]);
            app(PmdDocumentAudit::class)->record($id, 'DOCUMENT_DEADLINE_EXPIRED', [
                'previous_status' => $pmd->documentation_status, 'deadline' => $deadline->toDateTimeString(),
                'no_show' => $pmd->document_submission_method === 'IN_PERSON' && !$hasDocuments,
            ], 'SYSTEM');

            return true;
        });
    }

    public function hasCompleteTimelyDelivery(int $id, int $processId, Carbon $deadline): bool
    {
        $required = DB::table('process_document_types as policy')
            ->join('preregistration_document_types as type', 'type.id', '=', 'policy.document_type_id')
            ->where('policy.process_id', $processId)->where('policy.required', true)
            ->where('type.active', true)->pluck('type.id');
        $documents = DB::table('preregistration_documents')->where('preregistration_id', $id)
            ->orderByDesc('id')->get()->unique('document_type_id')->keyBy('document_type_id');

        return $required->isNotEmpty() && $required->every(function ($typeId) use ($documents, $deadline) {
            $document = $documents->get($typeId);

            return $document && in_array($document->status, ['PENDING', 'UNDER_REVIEW', 'APPROVED'])
                && Carbon::parse($document->created_at)->lte($deadline);
        });
    }
}

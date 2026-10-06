<?php

namespace App\EnrollmentRequests;

use App\Models\RegistrationRequest;
use iEducar\Packages\PreMatricula\Events\PreRegistrationStatusUpdatedEvent;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\DB;

class PmdBridge
{
    public function sync(RegistrationRequest $request): void
    {
        if (!$request->pmd_preregistration_id) {
            return;
        }
        $pmd = PreRegistration::query()->findOrFail($request->pmd_preregistration_id);
        $before = $pmd->status;
        $pmd->status = match ($request->status) {
            RequestStatus::Registered => PreRegistration::STATUS_ACCEPTED,
            RequestStatus::Rejected, RequestStatus::Cancelled => PreRegistration::STATUS_REJECTED,
            RequestStatus::Approved, RequestStatus::VacancyConfirmed => $request->workflow_version === 2 && $request->integration_status !== 'INTEGRATED'
                ? PreRegistration::STATUS_SUMMONED : PreRegistration::STATUS_IN_CONFIRMATION,
            default => $before,
        };
        if ($request->registration_id) {
            $pmd->classroom_id = $request->school_class_id;
            $pmd->external_person_id = $request->student->ref_idpes;
        }
        $pmd->saveOrFail();
        if ($before !== $pmd->status) {
            event(new PreRegistrationStatusUpdatedEvent($pmd, $before, $pmd->status));
            if ($pmd->status === PreRegistration::STATUS_ACCEPTED) {
                $this->rejectCompetingApplications($pmd);
            }
        }
        $this->syncDocumentation($request);
    }

    private function rejectCompetingApplications(PreRegistration $accepted): void
    {
        $process = $accepted->process;
        $cpf = $accepted->student->cpf;
        if ($process->shouldNotReject() || !$cpf) {
            return;
        }
        $query = PreRegistration::withoutGlobalScope('bc_school_access')
            ->whereHas('student', fn ($query) => $query->where('cpf', $cpf))
            ->whereKeyNot($accepted->id)
            ->whereIn('preregistrations.status', [PreRegistration::STATUS_WAITING,
                PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION]);
        if ($process->shouldRejectInSameProcess()) {
            $query->where('process_id', $accepted->process_id);
        } elseif ($process->shouldRejectInSameYear()) {
            $query->whereHas('process.schoolYear', fn ($query) => $query->where('year', $process->schoolYear->year));
        }
        foreach ($query->get() as $other) {
            $before = $other->status;
            $other->reject("Indeferida após o deferimento do protocolo {$accepted->protocol}.");
            $other->saveOrFail();
            event(new PreRegistrationStatusUpdatedEvent($other, $before, $other->status));

        }
    }

    public function syncDocumentation(RegistrationRequest $request): void
    {
        if (!$request->pmd_preregistration_id) {
            return;
        }
        $status = match ($request->status) {
            RequestStatus::DocumentsSent, RequestStatus::Corrected, RequestStatus::AwaitingReview => 'AWAITING_REVIEW',
            RequestStatus::UnderReview => 'UNDER_REVIEW',
            RequestStatus::Pending => 'CORRECTION_REQUIRED',
            RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered => 'APPROVED',
            RequestStatus::Expired, RequestStatus::NoShow => 'DEADLINE_EXPIRED',
            default => 'AWAITING_DOCUMENTS',
        };
        DB::table('preregistrations')->where('id', $request->pmd_preregistration_id)->update([
            'documentation_status' => $status,
            'documentation_deadline' => $request->document_deadline,
            'document_submission_method' => $request->attendance_mode ? ($request->attendance_mode === AttendanceMode::InPerson ? 'IN_PERSON' : 'ONLINE') : null,
        ]);
    }
}

<?php

namespace App\EnrollmentRequests;

use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\Auth;

class PmdDocumentSummary
{
    public function __invoke(PreRegistration $pmd): ?array
    {
        // The public PMD token must never grant access to school documentary data.
        $actor = Auth::guard('web')->user();
        if (!$actor instanceof User || !app(RequestAccess::class)->schools($actor)->whereKey($pmd->school_id)->exists()) {
            return null;
        }
        $request = RegistrationRequest::query()->visibleTo($actor)->where('pmd_preregistration_id', $pmd->id)->first();
        if (!$request) {
            return null;
        }
        if ($pmd->status === PreRegistration::STATUS_WAITING) {
            return [
                'released' => false, 'label' => 'Pré-matrícula em análise',
                'mode' => null, 'deadline' => null, 'required' => 0, 'received' => 0,
                'approved' => 0, 'corrections' => 0, 'enrolled' => false, 'triageUrl' => null,
            ];
        }
        $documents = $request->documents()->where('required', true)
            ->where('status', '!=', DocumentStatus::Replaced->value)->get();
        $timely = $documents->filter(fn ($document) => $document->received_at
            && $request->document_deadline && DocumentDeadlines::timely($document, $request));
        $received = $timely->count();
        $request->required_received = $received;

        return [
            'released' => true,
            'label' => $request->documentationLabel(),
            'mode' => $request->attendance_mode?->value,
            'deadline' => $request->document_deadline,
            'required' => $documents->count(), 'received' => $received,
            'approved' => $timely->where('status', DocumentStatus::Approved)->count(),
            'corrections' => $documents->filter(fn ($document) => in_array($document->status, [DocumentStatus::Correction, DocumentStatus::Rejected]))->count(),
            'enrolled' => $request->status === RequestStatus::Registered && $request->registration_id !== null,
            'triageUrl' => route('bc-registration.show', $request),
        ];
    }
}

<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyRegistration;
use App\Models\RegistrationRequest;
use Illuminate\Validation\ValidationException;

class NativePhysicalGuard
{
    public function assertConfirmed(LegacyRegistration $registration): void
    {
        $request = RegistrationRequest::query()->where('workflow_version', 2)
            ->where('intermediate_registration_id', $registration->getKey())->first();
        if ($request && (!$request->physical_confirmed_at || !$request->physical_confirmed_by
            || !in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed, RequestStatus::Registered]))) {
            throw ValidationException::withMessages(['physical' => 'Conclua a conferência física na Triagem Documental antes de promover ou enturmar esta pré-matrícula.']);
        }
    }
}

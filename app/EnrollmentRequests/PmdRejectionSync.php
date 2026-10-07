<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyRegistration;
use App\Models\RegistrationRequest;
use App\Models\RegistrationStatus;
use App\Services\RegistrationService;
use App\User;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmdRejectionSync
{
    public function sync(PreRegistration $pmd): void
    {
        DB::transaction(function () use ($pmd) {
            $request = RegistrationRequest::query()->where('pmd_preregistration_id', $pmd->id)->lockForUpdate()->first();
            if (!$request || in_array($request->status, [RequestStatus::Rejected, RequestStatus::Cancelled])) {
                return;
            }
            if ($request->registration_id || $request->status === RequestStatus::Registered) {
                throw ValidationException::withMessages(['registration' => 'A matrícula já foi efetivada. Utilize o movimento nativo para alterar sua situação.']);
            }
            $actor = Auth::guard('web')->user();
            if ($request->workflow_version === 2 && $request->intermediate_registration_id) {
                $native = LegacyRegistration::query()->lockForUpdate()->findOrFail($request->intermediate_registration_id);
                if ($native->aprovado !== RegistrationStatus::PRE_REGISTRATION
                    || $native->ref_cod_aluno != $request->student_id
                    || $native->ref_ref_cod_escola != $request->school_id
                    || $native->ref_ref_cod_serie != $request->grade_id
                    || (int) $native->ano !== (int) $request->school_year
                    || $native->activeEnrollments()->where('ref_cod_turma', '!=', $request->school_class_id)->exists()) {
                    throw ValidationException::withMessages(['registration' => 'O vínculo nativo foi alterado. Confira a matrícula antes de indeferir.']);
                }
                $actor ??= User::query()->findOrFail($native->ref_usuario_cad);
                if ($native->ativo) {
                    (new RegistrationService($actor))->cancelRegistration($native);
                }
            }
            $before = $request->status->value;
            $request->update(['status' => RequestStatus::Rejected, 'rejected_at' => now(), 'updated_by' => $actor?->getKey()]);
            app(RegistrationWorkflow::class)->event($request, EventType::Rejected, $actor,
                metadata: ['reason' => $pmd->observation, 'previous_status' => $before, 'source' => 'PMD']);
            app(PmdBridge::class)->syncDocumentation($request);
        });
    }
}

<?php

namespace App\EnrollmentRequests;

use App\Models\LegacyIndividual;
use App\Models\LegacyRegistration;
use App\Models\LegacyStudent;
use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeclaredStudentData
{
    public const FIELDS = [
        'name' => 'Nome completo', 'cpf' => 'CPF', 'date_of_birth' => 'Data de nascimento',
        'gender' => 'Sexo', 'rg' => 'RG', 'birth_certificate' => 'Certidão de nascimento',
        'phone' => 'Telefone', 'mobile' => 'Celular',
    ];

    public function initialize(int $pmdId): void
    {
        DB::table('bc_declared_data_reviews')->insertOrIgnore([
            'pmd_id' => $pmdId, 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function reviewFor(int $pmdId): ?object
    {
        return DB::table('bc_declared_data_reviews')->where('pmd_id', $pmdId)->first();
    }

    public function assertApproved(RegistrationRequest $request): void
    {
        $review = $request->pmd_preregistration_id ? $this->reviewFor($request->pmd_preregistration_id) : null;
        if ($review && $review->status !== 'APPROVED') {
            throw ValidationException::withMessages(['declared_data' => 'Confira e aprove os dados declarados antes da aprovação documental e da efetivação.']);
        }
    }

    public function review(RegistrationRequest $request, User $actor, bool $approved, ?string $reason, array $identities = []): void
    {
        DB::transaction(function () use ($request, $actor, $approved, $reason, $identities) {
            $request = RegistrationRequest::query()->lockForUpdate()->findOrFail($request->id);
            app(RequestAccess::class)->authorize($actor, $request);
            abort_unless($request->pmd_preregistration_id, 422);
            $identities = array_filter(array_intersect_key($identities, array_flip(['native_student_person_id', 'native_guardian_person_id'])));
            $identityResolution = $approved && $identities && $this->reviewFor($request->pmd_preregistration_id)?->status === 'APPROVED';
            if ($request->status->terminal() || (in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed]) && !$identityResolution && $approved)) {
                throw ValidationException::withMessages(['declared_data' => 'A decisão cadastral já foi encerrada.']);
            }
            $status = $request->preregistration->status;
            if (!in_array($status, [PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])
                || (!$approved && !trim((string) $reason))) {
                throw ValidationException::withMessages(['declared_data' => 'A conferência exige pré-matrícula deferida e motivo para solicitar correção.']);
            }
            if ($identities) {
                abort_unless($actor->isAdmin() || $actor->isInstitutional(), 403);
                if (!$approved || !trim((string) $reason)) {
                    throw ValidationException::withMessages(['identity' => 'A resolução de identidade exige confirmação e justificativa.']);
                }
                foreach ($identities as $field => $id) {
                    abort_unless(LegacyIndividual::query()->whereKey($id)->exists(), 422);
                }
                if (!empty($identities['native_student_person_id'])) {
                    $student = LegacyStudent::query()->where('ref_idpes', $identities['native_student_person_id'])->first();
                    if ($student && !$actor->isAdmin()) {
                        abort_unless(LegacyRegistration::query()->where('ref_cod_aluno', $student->getKey())
                            ->whereIn('ref_ref_cod_escola', app(RequestAccess::class)->schools($actor)->select('cod_escola'))->exists()
                            || RegistrationRequest::query()->visibleTo($actor)->where('student_id', $student->getKey())->exists(), 403);
                    } elseif (!$student && !$actor->isAdmin()) {
                        abort(403);
                    }
                }
                if (!empty($identities['native_guardian_person_id']) && !$actor->isAdmin()) {
                    $individual = !empty($identities['native_student_person_id'])
                        ? LegacyIndividual::query()->find($identities['native_student_person_id']) : null;
                    abort_unless($individual && in_array((int) $identities['native_guardian_person_id'], array_map('intval', [
                        $individual->idpes_mae, $individual->idpes_pai, $individual->idpes_responsavel,
                    ]), true)
                        || (!empty($identities['native_student_person_id']) && RegistrationRequest::query()->visibleTo($actor)
                            ->whereHas('student', fn ($query) => $query->where('ref_idpes', $identities['native_student_person_id']))
                            ->where('guardian_id', $identities['native_guardian_person_id'])->exists()), 403);
                }
                $this->audit($request->pmd_preregistration_id, 'OPERATOR', $actor->getKey(), 'IDENTITY_CONFIRMED', $identities, $reason);
            }
            if (!$approved && in_array($request->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])) {
                $request->update(['status' => RequestStatus::AwaitingReview, 'approved_at' => null, 'updated_by' => $actor->getKey()]);
                app(PmdBridge::class)->syncDocumentation($request);
                $this->audit($request->pmd_preregistration_id, 'OPERATOR', $actor->getKey(), 'GENERAL_APPROVAL_REOPENED', [], $reason);
            }
            $previousReview = $this->reviewFor($request->pmd_preregistration_id);
            $this->initialize($request->pmd_preregistration_id);
            DB::table('bc_declared_data_reviews')->where('pmd_id', $request->pmd_preregistration_id)->update([
                'status' => $approved ? 'APPROVED' : 'CORRECTION', 'reason' => $reason,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'updated_at' => now(),
                'native_student_person_id' => $approved ? ($identities['native_student_person_id'] ?? $previousReview?->native_student_person_id) : null,
                'native_guardian_person_id' => $approved ? ($identities['native_guardian_person_id'] ?? $previousReview?->native_guardian_person_id) : null,
            ]);
            $this->audit($request->pmd_preregistration_id, 'OPERATOR', $actor->getKey(),
                $approved ? 'DATA_CONFIRMED' : 'DATA_CORRECTION_REQUESTED', [], $reason);
        });
    }

    public function update(int $profileId, int $pmdId, array $student, string $reason): void
    {
        DB::transaction(function () use ($profileId, $pmdId, $student, $reason) {
            app(GuardianProfiles::class)->authorize($profileId, $pmdId);
            $application = RegistrationRequest::query()->where('pmd_preregistration_id', $pmdId)->lockForUpdate()->first();
            DB::table('preregistrations')->where('id', $pmdId)->lockForUpdate()->firstOrFail();
            $pmd = PreRegistration::query()->with('student')->findOrFail($pmdId);
            $review = $this->reviewFor($pmdId);
            if (!in_array($pmd->status, [PreRegistration::STATUS_WAITING, PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION])
                || ($application && ($application->status->terminal() || in_array($application->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])))
                || ($pmd->status !== PreRegistration::STATUS_WAITING && $review?->status !== 'CORRECTION')) {
                throw ValidationException::withMessages(['declared_data' => 'Após o deferimento, os dados só podem ser alterados quando a escola solicitar correção cadastral.']);
            }
            if ($application?->workflow_version === 2 && $application->intermediate_registration_id) {
                $physical = DB::table('bc_physical_reviews')->where('registration_request_id', $application->id)
                    ->where('subject', 'cadastro')->lockForUpdate()->first();
                if ($physical?->status === 'PENDING' && (!$physical->deadline || Carbon::parse($physical->deadline)->lt(now()))) {
                    throw ValidationException::withMessages(['declared_data' => 'Prazo de regularização cadastral vencido. Solicite à escola a reabertura motivada do prazo.']);
                }
            }
            $changes = [];
            $snapshot = $pmd->student->replicate();
            foreach (self::FIELDS as $field => $label) {
                if (!array_key_exists($field, $student)) {
                    continue;
                }
                $before = $field === 'date_of_birth' ? $pmd->student->date_of_birth?->format('Y-m-d') : $pmd->student->{$field};
                $after = $student[$field];
                if ((string) $before !== (string) $after) {
                    $changes[$field] = ['before' => $before, 'after' => $after];
                }
                $snapshot->{$field} = $after;
            }
            if (!$changes) {
                return;
            }
            // PMD snapshots can be shared by alternative schools. Preserve other applications.
            // Preserve the prior identity hint for operator resolution; it never authorizes a merge.
            $snapshot->external_person_id = $pmd->student->external_person_id;
            $snapshot->saveOrFail();
            foreach ($pmd->student->addresses as $address) {
                $copy = $address->replicate();
                $copy->person_id = $snapshot->id;
                $copy->saveOrFail();
            }
            $pmd->student_id = $snapshot->id;
            $pmd->external_person_id = null;
            $pmd->saveOrFail();
            $this->initialize($pmdId);
            DB::table('bc_declared_data_reviews')->where('pmd_id', $pmdId)->update([
                'status' => 'PENDING', 'reason' => null, 'reviewed_at' => null, 'reviewed_by' => null, 'updated_at' => now(),
                'native_student_person_id' => null, 'native_guardian_person_id' => null,
            ]);
            $this->audit($pmdId, 'GUARDIAN', $profileId, 'DECLARED_DATA_CHANGED', $changes, $reason);
        });
    }

    private function audit(int $pmdId, string $actorType, int $actorId, string $event, array $changes, ?string $reason): void
    {
        DB::table('bc_declared_data_events')->insert([
            'pmd_id' => $pmdId, 'actor_type' => $actorType, 'actor_id' => $actorId,
            'event' => $event, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
            'reason' => $reason, 'created_at' => now(),
        ]);
    }
}

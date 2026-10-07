<?php

namespace App\Providers;

use App\EnrollmentRequests\AcceptPmdRegistrations;
use App\EnrollmentRequests\NewPmdRegistration;
use App\EnrollmentRequests\PmdDocumentAudit;
use App\EnrollmentRequests\PmdRejectionSync;
use App\EnrollmentRequests\RejectPmdInBatch;
use App\EnrollmentRequests\RejectPmdRegistrations;
use App\EnrollmentRequests\RequestAccess;
use App\EnrollmentRequests\UpdatePmdRegistration;
use App\Http\Controllers\PmdSessionController;
use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\NewPreRegistration;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\RejectInBatch;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\RejectPreRegistrations;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\UpdatePreRegistration;
use iEducar\Packages\PreMatricula\Http\Controllers\AuthController;
use iEducar\Packages\PreMatricula\Models\Field;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use iEducar\Packages\PreMatricula\Models\ProcessField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class BcPmdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(AcceptPreRegistrations::class)) {
            $this->app->bind(AcceptPreRegistrations::class, AcceptPmdRegistrations::class);
            $this->app->bind(RejectPreRegistrations::class, RejectPmdRegistrations::class);
            $this->app->bind(RejectInBatch::class, RejectPmdInBatch::class);
            $this->app->bind(NewPreRegistration::class, NewPmdRegistration::class);
            $this->app->bind(UpdatePreRegistration::class, UpdatePmdRegistration::class);
            $this->app->bind(AuthController::class, PmdSessionController::class);
        }
    }

    public function boot(): void
    {
        if (!class_exists(PreRegistration::class)) {
            return;
        }
        // Keep historical answers, but omit documentary choice from the intake form and its field configuration.
        ProcessField::addGlobalScope('bc_document_choice_after_release', fn ($query) => $query->whereNotIn('process_fields.field_id', Field::query()->where('internal', 'bc_attendance_mode')->select('id')));
        PreRegistration::created(function ($pmd) {
            if (!$pmd->parent_id && !$pmd->isWaitingList()) {
                app(PmdDocumentAudit::class)->record($pmd->id, 'PREREGISTRATION_REGISTERED');
            }
        });
        PreRegistration::addGlobalScope('bc_school_access', function ($query) {
            $actor = Auth::guard('web')->user();
            if ($actor instanceof User) {
                $query->whereIn('preregistrations.school_id', app(RequestAccess::class)
                    ->schools($actor)->select('cod_escola'));
            }
        });
        PreRegistration::saving(function ($pmd) {
            $actor = Auth::guard('web')->user();
            if ($actor instanceof User) {
                app(RequestAccess::class)->authorizeSchool($actor, $pmd->school_id);
            }
            $changesIntake = $pmd->isDirty(['school_id', 'grade_id', 'period_id'])
                || ($pmd->isDirty('status') && $pmd->status === PreRegistration::STATUS_WAITING);
            if ($pmd->exists && $changesIntake && (in_array($pmd->getOriginal('status'), [
                PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION, PreRegistration::STATUS_ACCEPTED,
            ]) || RegistrationRequest::query()->where('pmd_preregistration_id', $pmd->id)
                ->whereHas('events', fn ($query) => $query->where('event', 'PREREGISTRATION_APPROVED'))->exists())) {
                throw ValidationException::withMessages([
                    'preregistration' => 'A pré-matrícula já foi deferida. Continue na etapa de documentação; escola, série, turno e deferimento estão bloqueados.',
                ]);
            }
            if ($pmd->exists && $pmd->isDirty('status') && $pmd->status === PreRegistration::STATUS_REJECTED) {
                app(PmdRejectionSync::class)->sync($pmd);
            }
        });
    }
}

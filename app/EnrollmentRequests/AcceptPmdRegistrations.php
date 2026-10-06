<?php

namespace App\EnrollmentRequests;

use App\Models\LegacySchoolClass;
use App\Models\RegistrationRequest;
use App\User;
use iEducar\Packages\PreMatricula\Events\PreRegistrationStatusUpdatedEvent;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\AcceptPreRegistrations;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptPmdRegistrations extends AcceptPreRegistrations
{
    // Schema validation runs without an authenticated user; the workflow resolves its actor during a mutation.
    public function __construct() {}

    public function __invoke($_, array $args)
    {
        return DB::transaction(function () use ($args) {
            $actor = Auth::user();
            abort_unless($actor instanceof User && $actor->ativo && $actor->employee?->ativo, 403);
            $class = LegacySchoolClass::query()->findOrFail($args['classroom']);
            $access = app(RequestAccess::class);
            $result = [];
            foreach ($args['ids'] as $id) {
                $pmd = PreRegistration::query()->findOrFail($id);
                // Authorization uses the native school scope, including for upstream requests without a BC link.
                $access->authorizeSchool($actor, $pmd->school_id);
                abort_unless($class->school_id == $pmd->school_id, 403);
                $request = RegistrationRequest::query()->where('pmd_preregistration_id', $id)->first();
                if ($request) {
                    $access->authorize($actor, $request);
                    if ($request->school_class_id != $class->getKey()) {
                        throw ValidationException::withMessages(['classroom' => 'A turma deve corresponder à solicitação documental.']);
                    }
                    if (!in_array($pmd->status, [PreRegistration::STATUS_WAITING, PreRegistration::STATUS_SUMMONED,
                        PreRegistration::STATUS_IN_CONFIRMATION])
                        && !($pmd->status === PreRegistration::STATUS_ACCEPTED && $request->status === RequestStatus::Registered)) {
                        throw ValidationException::withMessages(['pmd' => 'A pré-matrícula encerrada não pode liberar documentação.']);
                    }
                    // Deferment approves intake only, never native enrollment.
                    if ($pmd->status === PreRegistration::STATUS_WAITING && !$request->status->terminal()) {
                        $before = $pmd->status;
                        $pmd->summon();
                        $pmd->saveOrFail();
                        event(new PreRegistrationStatusUpdatedEvent($pmd, $before, $pmd->status));
                    }
                    if (!$request->status->terminal() && !$request->events()->where('event', EventType::PreRegistrationApproved->value)->exists()) {
                        app(RegistrationWorkflow::class)->event($request, EventType::PreRegistrationApproved, $actor);
                    }
                    if (!$request->status->terminal() && !$request->events()->where('event', EventType::DocumentsReleased->value)->exists()) {
                        app(RegistrationWorkflow::class)->event($request, EventType::DocumentsReleased, $actor);
                    }
                    $result[] = $pmd->fresh();
                } else {
                    app(PmdIntake::class)->import((int) $id, (int) $class->getKey(), $actor, $pmd->document_submission_method === 'IN_PERSON' ? AttendanceMode::InPerson : null);
                    $result[] = $pmd->fresh();
                }
            }

            return $result;
        });
    }
}

<?php

namespace App\EnrollmentRequests;

use App\User;
use iEducar\Packages\PreMatricula\GraphQL\Mutations\UpdatePreRegistration;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePmdRegistration extends UpdatePreRegistration
{
    public function __invoke($_, array $args): PreRegistration
    {
        return DB::transaction(function () use ($_, $args) {
            $actor = Auth::user();
            abort_unless($actor instanceof User && $actor->ativo && $actor->employee?->ativo, 403);
            $row = DB::table('preregistrations')->where('protocol', $args['protocol'])->lockForUpdate()->firstOrFail();
            app(RequestAccess::class)->authorizeSchool($actor, $row->school_id);
            if ($row->status !== PreRegistration::STATUS_WAITING) {
                throw ValidationException::withMessages(['preregistration' => 'A pré-matrícula já foi analisada. O deferimento não pode ser editado; continue na documentação.']);
            }
            app(RequestAccess::class)->authorizeSchool($actor, (int) $args['school']);

            return parent::__invoke($_, $args);
        });
    }
}

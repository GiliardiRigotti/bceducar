<?php

namespace App\EnrollmentRequests;

use App\Models\LegacySchool;
use App\Models\LegacyUserSchool;
use App\Models\RegistrationRequest;
use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class RequestAccess
{
    public function scope(Builder $query, User $user): Builder
    {
        return $query->whereIn('school_id', $this->schools($user)->select('cod_escola'));
    }

    public function schools(User $user): Builder
    {
        $query = LegacySchool::query();
        if (!$user->ativo || !$user->employee?->ativo) {
            return $query->whereRaw('1 = 0');
        }
        // Even administrators are limited to their institution unless they are global admins.
        if ($user->isAdmin()) {
            return $query;
        }
        $query->where('ref_cod_instituicao', $user->ref_cod_instituicao);
        if ($user->isInstitutional()) {
            return $query;
        }
        if (!$user->isSchooling()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('cod_escola', LegacyUserSchool::query()
            ->select('ref_cod_escola')->where('ref_cod_usuario', $user->getKey()));
    }

    public function authorizeSchool(User $user, int $schoolId): void
    {
        if (!$this->schools($user)->whereKey($schoolId)->exists()) {
            throw new AuthorizationException('Sem autorização para esta unidade escolar.');
        }
    }

    public function authorize(User $user, RegistrationRequest $request): void
    {
        if (!RegistrationRequest::query()->visibleTo($user)->whereKey($request->id)->exists()) {
            throw new AuthorizationException('Sem autorização para esta unidade escolar.');
        }
    }
}

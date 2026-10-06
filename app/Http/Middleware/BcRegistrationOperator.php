<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;

class BcRegistrationOperator
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->ativo && $user->employee?->ativo
            && ($user->isAdmin() || $user->isInstitutional() || $user->isSchooling()), 403);

        return $next($request);
    }
}

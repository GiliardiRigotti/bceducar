<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\GraphQL\Mutations\RejectPreRegistrations;
use Illuminate\Support\Facades\DB;

class RejectPmdRegistrations extends RejectPreRegistrations
{
    public function __invoke($_, array $args)
    {
        return DB::transaction(fn () => parent::__invoke($_, $args));
    }
}

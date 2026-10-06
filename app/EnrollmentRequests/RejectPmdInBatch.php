<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\GraphQL\Mutations\RejectInBatch;
use Illuminate\Support\Facades\DB;

class RejectPmdInBatch extends RejectInBatch
{
    public function __invoke($_, array $args)
    {
        return DB::transaction(fn () => parent::__invoke($_, $args));
    }
}

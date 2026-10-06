<?php

namespace App\EnrollmentRequests;

use iEducar\Packages\PreMatricula\GraphQL\Mutations\NewPreRegistration;
use iEducar\Packages\PreMatricula\Models\Field;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NewPmdRegistration extends NewPreRegistration
{
    public function __invoke(mixed $_, array $args): array
    {
        $blocked = Field::query()->where('internal', 'bc_attendance_mode')->pluck('id')
            ->map(fn ($id) => 'field_' . $id)->push('bc_attendance_mode')->all();
        foreach (['student', 'responsible'] as $person) {
            foreach ($args[$person] ?? [] as $field) {
                if (in_array($field['field'] ?? null, $blocked)) {
                    throw ValidationException::withMessages(['documents' => 'A modalidade documental só pode ser escolhida após o deferimento.']);
                }
            }
        }

        return DB::transaction(fn () => parent::__invoke($_, $args));
    }
}

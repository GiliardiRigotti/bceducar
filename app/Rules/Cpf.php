<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Portabilis_Utils_Validation;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)
            || !preg_match('/^(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})$/', $value)
            || !Portabilis_Utils_Validation::validatesCpf($value)) {
            $fail('Informe um CPF válido ou deixe o campo em branco.');
        }
    }
}

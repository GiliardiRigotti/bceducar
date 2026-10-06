<?php

namespace App\EnrollmentRequests;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

class DocumentCodeCast implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): DocumentType|DocumentCode
    {
        return DocumentType::tryFrom($value) ?? new DocumentCode($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return DocumentCode::value($value);
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return DocumentCode::value($value);
    }
}

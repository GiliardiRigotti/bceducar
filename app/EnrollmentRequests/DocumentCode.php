<?php

namespace App\EnrollmentRequests;

final readonly class DocumentCode
{
    public function __construct(public string $value) {}

    public static function value(DocumentType|string|self $type): string
    {
        return is_string($type) ? $type : $type->value;
    }
}

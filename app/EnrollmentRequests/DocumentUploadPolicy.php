<?php

namespace App\EnrollmentRequests;

class DocumentUploadPolicy
{
    public static function maxKilobytes(): int
    {
        $value = filter_var(config('bc-documents.max_upload_kb', 5120), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);

        return $value === false ? 5120 : $value;
    }

    public static function rules(): array
    {
        return ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:' . self::maxKilobytes()];
    }

    public static function label(): string
    {
        return number_format(self::maxKilobytes() / 1024, 2, ',', '.') . ' MB';
    }
}

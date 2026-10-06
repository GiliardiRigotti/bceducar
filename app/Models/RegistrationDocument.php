<?php

namespace App\Models;

use App\EnrollmentRequests\DocumentCodeCast;
use App\EnrollmentRequests\DocumentStatus;
use Illuminate\Database\Eloquent\Model;

class RegistrationDocument extends Model
{
    protected $table = 'bc_registration_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'document_type' => DocumentCodeCast::class,
            'required' => 'boolean', 'received_at' => 'datetime',
            'delivery_deadline' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function documentName(): string
    {
        return $this->document_name ?: str_replace('_', ' ', $this->document_type->value);
    }

    public function request()
    {
        return $this->belongsTo(RegistrationRequest::class, 'registration_request_id');
    }
}

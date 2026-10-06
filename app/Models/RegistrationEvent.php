<?php

namespace App\Models;

use App\EnrollmentRequests\EventType;
use Illuminate\Database\Eloquent\Model;

class RegistrationEvent extends Model
{
    protected $table = 'bc_registration_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event' => EventType::class, 'metadata' => 'array', 'created_at' => 'datetime'];
    }
}

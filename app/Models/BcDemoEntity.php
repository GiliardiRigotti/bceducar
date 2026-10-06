<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BcDemoEntity extends Model
{
    protected $table = 'bc_demo_entities';

    public $timestamps = false;

    protected $guarded = ['id'];
}

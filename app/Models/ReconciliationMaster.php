<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationMaster extends Model
{
    protected $fillable = [
        'user_id',
        'month',
        'year',
        'uuid',
        'confirmed'
    ];
}

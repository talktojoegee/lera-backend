<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeRate extends Model
{
    protected $fillable = [
        'rate',
        'occupancy',
    ];
}

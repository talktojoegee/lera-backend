<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relief extends Model
{
    protected $fillable = [
        'description',
        'item',
        'rate'
    ];
}

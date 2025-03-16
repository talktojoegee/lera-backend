<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementReportSetup extends Model
{
    protected $fillable = [
        'bank',
        'newwaves',
        'kgirs',
        'lga',
    ];
}

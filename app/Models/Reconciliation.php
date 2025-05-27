<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    //
    protected $fillable = [
        'user_id',
        'entry_date',
        'details',
        'value_date',
        'debit',
        'credit',
        'balance',
        'month_year',
        'month',
        'year',
        'payer_name',
        'assessment_no',
    ];
}

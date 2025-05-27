<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualReceipt extends Model
{
    protected $fillable = [
        'issued_by',
        'assessment_no',
        'amount',
        'proof_of_payment',
        'status',
        'actioned_by',
        'date_actioned',
        'receipt_no',
        'entry_date',
        'bank_name',
        'branch_name',
        'customer_name',
        'email',
        'kgtin',
        'reference',
        'token',
        'url',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPaymentLog extends Model
{
    protected $fillable = [
        "bill_master",
        "paid_by",
        "amount",
        "trans_ref",
        "reference",
    ];
}

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
        "receipt_no",
        "payment_code",
        "assessment_no",
        "bank_name",
        "branch_name",
        "pay_mode",
        "customer_name",
        "email",
        "kgtin",
    ];
}

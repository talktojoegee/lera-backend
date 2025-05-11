<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KogiRemsNotification extends Model
{
    protected $fillable = [
        "assessmentno",
        "buildingcode",
        "kgtin",
        "name",
        "amount",
        "phone",
        "email",
        "transdate",
        "transRef",
        "paymode",
        "bank_name",
        "luc_amount"
    ];
}

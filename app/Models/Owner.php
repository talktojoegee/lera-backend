<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    protected $fillable = [
        "email",
        "kgtin",
        "name",
        "res_address",
        "telephone",
        "lga_id",
        "added_by",
    ];

    public function getLGA(){
        return $this->belongsTo(Lga::class, 'lga_id');
    }
}

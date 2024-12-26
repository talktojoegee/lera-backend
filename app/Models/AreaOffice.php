<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaOffice extends Model
{
    protected $fillable = [
        'area_name',
        'area_office_id',
        'lga_id'
    ];


    public function getLGA(){
        return $this->belongsTo(Lga::class, 'lga_id');
    }
}

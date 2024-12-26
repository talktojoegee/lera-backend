<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lga extends Model
{
    protected $fillable = [
        'lga_name'
    ];


    public static function fetchAllLGAs(){
        return Lga::orderBy('lga_name', 'ASC')->get();
    }



}

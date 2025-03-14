<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;

trait AVMLUCTrait
{


    /**
     * Calculates Assessed Market Value
     *
     * @param  double  $la (value)
     * @param  double  $lr (value)
     * @param  double  $br (value)
     * @param  double   $ba (%)
     * @param  double   $dr (%)
     * @param  double   $rr (%)
     * @param  double   $convert (%)
     * @return void
     */
    public function calculateAMV($la, $lr, $br, $ba, $dr, $rr, $convert){
        $assessVal = ( ($la * $lr) + ($ba * $br * $dr) ) * ($rr);
        //$luc = ( ($la * $lr) + ($ba * $br * $dr) ) * ($rr * $cr);
       // return $assessVal;
    }



}

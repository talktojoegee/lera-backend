<?php
use App\Traits\UtilityTrait;

if (!function_exists('numberToWords')) {
    function numberToWords($num)
    {
        $trait = new class { use UtilityTrait; };
        return $trait->numberToWords($num);
    }
}

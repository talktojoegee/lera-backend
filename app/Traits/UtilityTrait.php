<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait UtilityTrait
{
    public function generateRandomUrls(Request $request)
    {
        $numberOfUrls = 10;
        $randomUrls = [];

        for ($i = 0; $i < $numberOfUrls; $i++) {
            $randomUrl = $this->generateRandomUrl();
            $randomUrls[] = $randomUrl;
        }
        return response()->json(['urls' => $randomUrls]);
    }

    private function generateRandomUrl()
    {
        $length = 8;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function median(array $data): float {
        sort($data);
        $count = count($data);
        $mid = floor($count / 2);

        if ($count % 2 === 1) {
            return $data[$mid];
        } else {
            return ($data[$mid - 1] + $data[$mid]) / 2;
        }
    }

    function convertToSqm($value) {
        if (str_ends_with($value, "_Acre")) {
            $number = (int) str_replace("_Acre", "", $value);
            $sqm = $number * 4046.86;
            return $sqm . "_sqm";
        } elseif (str_ends_with($value, "_sqm")) {
            return $value;
        } else {
            return "Invalid format";
        }
    }


}

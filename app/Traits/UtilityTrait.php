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


}

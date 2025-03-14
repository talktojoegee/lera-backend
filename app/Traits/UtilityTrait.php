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


    private function convertLessThanThousand($n, $ones, $teens, $tens) {
        $words = "";

        if ($n >= 100) {
            $words .= $ones[floor($n / 100)] . " hundred";
            $n %= 100;
            if ($n > 0) $words .= " and ";
        }

        if ($n >= 11 && $n <= 19) {
            $words .= $teens[$n - 10];
        } else {
            if ($n >= 20) {
                $words .= $tens[floor($n / 10)];
                if ($n % 10 > 0) $words .= "-" . $ones[$n % 10];
            } else if ($n > 0) {
                $words .= $ones[$n];
            }
        }

        return trim($words);
    }

    private function convertDecimal($decimalPart, $ones, $subCurrency) {
        if (!$decimalPart || intval($decimalPart) === 0) return "";
        $decimalWords = "";
        foreach (str_split($decimalPart) as $digit) {
            $decimalWords .= $ones[intval($digit)] . " ";
        }
        return trim($decimalWords) . " " . $subCurrency;
    }

    public function numberToWords($num, $mainCurrency = "naira", $subCurrency = "kobo") {
        if ($num == 0) return "zero";

        $ones = ["", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine"];
        $teens = ["", "eleven", "twelve", "thirteen", "fourteen", "fifteen", "sixteen", "seventeen", "eighteen", "nineteen"];
        $tens = ["", "ten", "twenty", "thirty", "forty", "fifty", "sixty", "seventy", "eighty", "ninety"];
        $thousands = ["", "thousand", "million", "billion", "trillion"];

        // Split number into integer and decimal parts
        $parts = explode(".", strval($num));
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : null;

        $wordString = "";
        $chunkCount = 0;
        $intNum = intval($integerPart);

        while ($intNum > 0) {
            $chunk = $intNum % 1000;
            if ($chunk > 0) {
                $chunkWords = $this->convertLessThanThousand($chunk, $ones, $teens, $tens);
                if ($chunkCount > 0) {
                    $chunkName = $thousands[$chunkCount] ?? "";
                    $wordString = $chunkWords . " " . $chunkName . ($wordString ? ", " . $wordString : "");
                } else {
                    $wordString = $chunkWords . ($wordString ? ", " . $wordString : "");
                }
            }
            $intNum = floor($intNum / 1000);
            $chunkCount++;
        }

        if ($decimalPart) {
            $decimalWords = $this->convertDecimal($decimalPart, $ones, $subCurrency);
            $wordString .= $decimalPart ? " and " . $decimalWords : "";
        }

        return ucfirst(trim($wordString . " " . $mainCurrency));
    }


}

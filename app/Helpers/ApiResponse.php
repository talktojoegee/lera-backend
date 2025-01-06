<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data, $message = "Success", $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error($message, $code = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}

<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

trait ResolvesActingUser
{
    protected function resolveActingUser(Request $request): ?User
    {
        $userId = $request->route('user')
            ?? $request->input('user')
            ?? $request->input('userId')
            ?? $request->input('actionedBy')
            ?? $request->input('auth')
            ?? $request->header('X-User-Id');

        if ($userId) {
            return User::find($userId);
        }

        try {
            $token = $request->bearerToken();

            if (! $token) {
                $authorization = (string) $request->header('Authorization', '');
                if (str_starts_with($authorization, 'Bearer ')) {
                    $token = trim(substr($authorization, 7));
                }
            }

            if ($token) {
                $user = JWTAuth::setToken($token)->authenticate();

                if ($user instanceof User) {
                    return $user;
                }

                // JWT `sub` is the user id, but this app authenticates by username.
                $userId = JWTAuth::setToken($token)->getPayload()->get('sub');

                return $userId ? User::find($userId) : null;
            }
        } catch (JWTException) {
            return null;
        }

        return null;
    }

    protected function actingUserNotFoundResponse()
    {
        return response()->json([
            'message' => 'Whoops! Something went wrong.',
            'detail' => 'Unable to identify the logged-in user. Pass ?user={id}, include user in the route, or send a valid Authorization Bearer token.',
        ], 404);
    }
}

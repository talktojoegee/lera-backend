<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class AuthenticationController extends Controller
{
/*
    public function __construct(){
        $this->middleware('auth:api', ['except'=>['login']]);
    }*/

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|unique:users,email',
            'password' => 'required|min:8',
        ],[
            "name.required"=>"Enter full name",
            "email.required"=>"Enter email address",
            "email.email"=>"Enter a valid email address",
            "email.unique"=>"This email address is already taken.",
            "username.unique"=>"This username is already taken.",
            "username.required"=>"Choose a unique username",
            "password.required"=>"Choose a password",
            "password.min"=>"Password length must be minimum of 8 characters",
        ]);

        if ($validator->fails()) {
            return ApiResponse::error("All fields are required", 422);
            //return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);
        $token = JWTAuth::fromUser($user);
        $user->token = $token;

        return new AuthUserResource($user); // response()->json(['message' => 'User registered successfully']);
    }

    public function authenticate(Request $request){
        $request->validate([
            "username"=>"required",
            "password"=>"required"
        ],[
            "username.required"=>"Enter your registered password",
            "password.required"=>"Enter your password for this account",
        ]);
        $username = $request->input('username');
        $password = $request->input('password');
        $user = User::where('username', $username)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return ApiResponse::error("Invalid login credentials.", 401);
        }
        $token = JWTAuth::fromUser($user);
        $user->token = $token;
        return new  AuthUserResource($user);

    }

    public function logout(Request $request)
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh());
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ]);
    }
}

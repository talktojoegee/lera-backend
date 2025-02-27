<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyProfileResource;
use App\Models\Owner;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function myProfile(Request $request){
        $userId = $request->uuid;
        $user = Owner::where("user_id",$userId)->first();
        if(empty($user)){
            return response()->json(['data'=>"Whoops! No record found"],404);
        }
        return new MyProfileResource($user);
    }
}

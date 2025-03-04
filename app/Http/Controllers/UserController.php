<?php

namespace App\Http\Controllers;

use App\Http\Resources\MyProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Objection;
use App\Models\ObjectionAttachment;
use App\Models\Owner;
use App\Models\Role;
use App\Models\User;
use App\Traits\EmailTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
use EmailTrait;



    public function showAllUsers(Request $request){
        $type = $request->type;
        $skip = $request->skip;
        $limit = $request->limit;
        $records = User::fetchAllAdminUsers($type, $limit, $skip);
        return response()->json([
            'data'=>UserResource::collection($records),
            'total'=>User::where('type', $type)->count()
        ]);
    }


    public function storeUser(Request $request){

        $validator = Validator::make($request->all(),
            [
                "username"=>"required",
                "name"=>"required",
                "email"=>"required|email",
                "mobileNo"=>"required",
                "idNo"=>"required",
                "sector"=>"required",
                "role"=>"required",
            ],
            [
                "username.required"=>"Username is required",
                "name.required"=>"Name is required",
                "email.required"=>"Email is required",
                "mobileNo.required"=>"Mobile number is required",
                "idNo.required"=>"ID number is required",
                "sector.required"=>"Sector is required",
                "role.required"=>"Role is required",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $uniqueNumber = time();
        $password = substr(sha1(time()),31,40);
        $user = User::create([
            'name'=>$request->name,
            'email'=>$request->email,
            'username'=>$request->username,
            'type'=>1,
            'password'=>bcrypt($password),
            'id_no'=>$request->idNo,
            'mobile_no'=>$request->mobileNo,
            'sector'=>implode(',',$request->sector),
            'role'=>$request->role,
            'lga'=>$request->lga
        ]);
        $role = Role::find($request->role);
        $data = [
            "name"=>$user->name,
            "plainPassword"=>$password,
            "username"=>$user->username,
            "roleName"=>!empty($role) ? $role->name : '',
        ];
        $this->sendEmail($user->email, 'Account Creation', 'emails.new-user', $data);
       // $x = "{""Recipients"":[{""Email"":""" & trim(x_Email) & """}],""Content"":{""Body"":[{""ContentType"":""HTML"",""Content"":""" & trim(x_Body) & """}],""From"":""KGIRS <info@aoctms.com.ng>"",""Subject"":""" & trim(x_Subject) & """}}";
        return response()->json(['message' => 'Success! Action successful.'], 201);
    }

    public function updateUser(Request $request){

        $validator = Validator::make($request->all(),
            [
                "username"=>"required",
                "name"=>"required",
                "email"=>"required|email",
                "mobileNo"=>"required",
                "idNo"=>"required",
                "sector"=>"required",
                "role"=>"required",
                "id"=>"required",
            ],
            [
                "username.required"=>"Username is required",
                "name.required"=>"Name is required",
                "email.required"=>"Email is required",
                "mobileNo.required"=>"Mobile number is required",
                "idNo.required"=>"ID number is required",
                "sector.required"=>"Sector is required",
                "role.required"=>"Role is required",
                "id.required"=>"",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        //$uniqueNumber = time();
        $user = User::find($request->id);
        if(empty($user)){
            return response()->json([
                "data"=>"No record found."
            ],404);
        }
        $user = User::where('id', $request->id)->update([
            'name'=>$request->name,
            'email'=>$request->email,
            //'username'=>$request->username,
            //'type'=>1,
            //'password'=>bcrypt("password123"),
            'id_no'=>$request->idNo,
            'mobile_no'=>$request->mobileNo,
            'sector'=>implode(',',$request->sector),
            'role'=>$request->role,
            'lga'=>$request->lga
        ]);

        return response()->json(['message' => 'Success! Action successful.'], 200);
    }



}

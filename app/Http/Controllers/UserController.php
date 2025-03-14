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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    /* public function changePassword(Request $request){
         $validator = Validator::make($request->all(),
             [
                 "currentPassword"=>"required",
                 "newPassword"=>"required",
                 "retypePassword"=>"required",
                 "user"=>"required"
             ],[
                 "currentPassword.required"=>"Enter your current password",
                 "newPassword.required"=>"Choose a new password",
                 "retypePassword.required"=>"Re-type chosen password",
                 "user.required"=>""
             ]
         );
         if($validator->fails() ){
             return response()->json([
                 "errors"=>$validator->messages()
             ],422);
         }

         $user = User::find($request->user);
         if(!$user){
             return response()->json(["error"=>"Whoops! Record not found"],404);
         }
         if (Hash::check($request->currentPassword, $user->password)) {
             $user->password = bcrypt($request->newPassword);
             $user->save();
             return response()->json(["data"=>"Password changed!"],200);
         }else{

             return response()->json(["error"=>"Current password does not match our record. Try again."],404);
         }
     }*/
    public function changePassword(Request $request){
        $validator = Validator::make($request->all(), [
            "currentPassword" => "required",
            "newPassword" => "required",
            "retypePassword" => "required",
            "user" => "required"
        ], [
            "currentPassword.required" => "Enter your current password",
            "newPassword.required" => "Choose a new password",
            "retypePassword.required" => "Re-type chosen password",
            "user.required" => "User field is required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages(),
                "message"=>"Validation error",
                "detail"=>"All fields are required."
            ], 422);
        }

        // Check if user exists
        $user = User::find($request->user);
        if (!$user) {
            return response()->json(["error" => "Whoops! Record not found"], 404);
        }
        /*  if ( !strcmp($request->newPassword, $user->retypePassword) ) {
              return response()->json(["error" => "Password confirmation failed"], 403);
          }
          if(strcmp($request->newPassword, 'password123')){
              return response()->json(["error" => "You can't change password to the default password."], 403);
          }*/

        if (!Hash::check($request->currentPassword, $user->password)) {
            return response()->json(["error" => "Current password does not match our record. Try again."], 403);
        }

        // Update password
        $user->password = bcrypt($request->newPassword);
        $user->defaulted_password = 0;
        $user->save();

        return response()->json(["message" => "Password changed successfully!"], 200);
    }

    public function resetPassword(Request $request){
        $userId = $request->user;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json(['error'=>"Record not found"],404);
        }
        $user->password = bcrypt('password123');
        $user->defaulted_password = 1;
        $user->save();
        return response()->json(['data'=>"Action successful"],200);
    }


//insert into `billings` (`building_code`, `assessment_no`, `assessed_value`, `bill_amount`, `minimum_luc`, `year`, `entry_date`, `billed_by`, `rr`, `lr`, `ba`, `br`, `dr`, `cr`, `dr_value`, `paid_amount`, `objection`, `lga_id`, `property_id`, `bill_rate`, `pav_code`, `zone_name`, `url`, `class_id`, `property_use`, `occupancy`, `la`, `updated_at`, `created_at`) values
//(Kg/AJA/705179, 67c8199543937, 7061400, 53666.64, 0, 2025, 2025-03-05 09:29:57, 1, 40, 12500, 60, 45000, 1, 0.76, 1, 0, 0, 2, 14617, 0, B0113, A3, 74430620541, 1, Detached Bungalow, 6, 450, 2025-03-05 09:29:57, 2025-03-05 09:29:57))




}

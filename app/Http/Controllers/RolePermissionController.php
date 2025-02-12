<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Lga;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{





    public function createRole(Request $request){
        $validator = Validator::make($request->all(),[
            "name"=>"required|unique:roles,name"
        ],[
            "name.required"=>"Role name is required"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        Role::create(['name'=>$request->name]);
        return response()->json(['message' => 'Success! Role added.'], 201);
    }

    public function showAllRoles(){
        return RoleResource::collection(Role::all());
    }


    public function createPermission(Request $request){
        $validator = Validator::make($request->all(),[
            "name"=>"required|unique:permissions,permission"
        ],[
            "name.required"=>"Role name is required"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        Permission::create(['permission'=>$request->name]);
        return response()->json(['message' => 'Success! Role added.'], 201);
    }

    public function showAllPermissions(){
        return PermissionResource::collection(Permission::all());
    }


}

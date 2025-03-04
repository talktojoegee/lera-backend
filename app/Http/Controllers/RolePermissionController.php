<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RolePermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Lga;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
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
        Role::create(['name'=>strtoupper($request->name)]);
        return response()->json(['message' => 'Success! Role added.'], 201);
    }

    public function updateRole(Request $request){
        $validator = Validator::make($request->all(),[
            "role"=>"required",
            "id"=>"required"
        ],[
            "role.required"=>"Role name is required",
            "id.required"=>""
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $role = Role::find($request->id);
        if(empty($role)){
            return response()->json([
                "data"=>"No record found"
            ],404);
        }
        $role->name = strtoupper($request->role) ?? '';
        if($role->id != 6){
            $role->save();
        }

        return response()->json(['message' => 'Success! Changes saved!'], 200);
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
        Permission::create(['permission'=>strtoupper(str_replace(' ', '_', $request->name)) ]);
        return response()->json(['message' => 'Success! Role added.'], 201);
    }


    public function showAllPermissions(){
        return PermissionResource::collection(Permission::all());
    }

    public function assignPermissionToRole(Request $request){
        $validator = Validator::make($request->all(),[
            "role"=>"required",
            "permissions"=>"required",
        ],[
            "role.required"=>"Role name is required",
            "permissions.required"=>"Permissions are required"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        foreach($request->permissions as $permission){
            RolePermission::create([
                'role_id'=>$request->role,
                'permission_id'=>$permission
            ]);
        }
        return response()->json(['message' => 'Success! Permissions assigned!'], 201);
    }


    public function showAllRolePermissions(){
        return RolePermissionResource::collection(RolePermission::getRoles());
    }


    public function updatePermissionToRole(Request $request){
        $validator = Validator::make($request->all(),[
            "role"=>"required",
            "permissions"=>"required",
        ],[
            "role.required"=>"Role name is required",
            "permissions.required"=>"Permissions are required"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $trimmedItems = array_map('trim', $request->permissions);
        $chosenPermissions = Permission::whereIn('permission', $trimmedItems)->get();
        //return response()->json(['data'=>$chosenPermissions],200);
        //$chosenPermissionIds = Permission::whereIn('permission', $request->permissions)->pluck('id')->toArray();
        $rolePermissions = RolePermission::where('role_id', $request->role)->get();
        foreach($rolePermissions as $rp) {
            $rp->delete();
        }
        foreach($chosenPermissions as $chosenPermission){
            RolePermission::create([
                'role_id'=>$request->role,
                'permission_id'=>$chosenPermission->id
            ]);
        }

        /*foreach($rolePermissions as $rp){
            if(!in_array($rp->permission_id, $chosenPermissionIds) ){
                $rp->delete();
            }
        }*/
        return response()->json(['message' => 'Action successful!'], 201);
    }


}

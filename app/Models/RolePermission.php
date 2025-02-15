<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RolePermission extends Model
{
    protected $fillable = [
        'role_id',
        'permission_id',
    ];


    public static function getRoles(){
        return DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->select(
                'roles.id as role_id',
                'roles.name as role_name',
                DB::raw('GROUP_CONCAT(DISTINCT permissions.permission ORDER BY permissions.permission ASC SEPARATOR ", ") as permissions')
            )
            ->groupBy('roles.id', 'roles.name') // Group by role_id and role_name to get distinct roles
            ->get();
    }



}

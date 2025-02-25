<?php

namespace App\Http\Resources;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissionIds = RolePermission::where('role_id', $this->role)->pluck('permission_id')->toArray();
        $permissions = Permission::whereIn('id', $permissionIds)->pluck('permission');
        $role = Role::find($this->role);

        return [
          "id"=>$this->id,
          "name"=>$this->name,
          "email"=>$this->email,
          "mobileNo"=>$this->mobile_no,
          "idNo"=>$this->id_no,
          "username"=>$this->username,
          "sector"=>$this->sector,
        'role' => $role->name ?? '',
        'roleId'=>$role->id,
        'permissions'=>$permissions ?? [],
        ];
    }
}

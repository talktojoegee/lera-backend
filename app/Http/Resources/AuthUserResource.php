<?php

namespace App\Http\Resources;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
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
        //return parent::toArray($request);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $role->name ?? '',
            'permissions'=>$permissions ?? [],
            'token' => $this->token,
            'type'=>$this->type,
            'defaultPassword'=>$this->defaulted_password
        ];
    }
}

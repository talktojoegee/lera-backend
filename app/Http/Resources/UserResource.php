<?php

namespace App\Http\Resources;

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
        return [
          "name"=>$this->name,
          "email"=>$this->email,
          "mobileNo"=>$this->mobile_no,
          "idNo"=>$this->id_no,
          "username"=>$this->username,
          "sector"=>$this->sector,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=>$this->id,
            "name"=>$this->name ?? '',
            "email"=>$this->email ?? '',
            "mobileNo"=>$this->telephone ?? '',
            "kgtin"=>$this->kgtin ?? '',
            "username"=>$this->username,
            "lgaName"=>$this->getLga->lga_name ?? '',
            "address"=>$this->res_address,
        ];
    }
}

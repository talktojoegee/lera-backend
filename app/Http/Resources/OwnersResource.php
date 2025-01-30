<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        return [
          "id"=>$this->id,
          "kgtin"=>$this->kgtin,
          "name"=>$this->name,
          "telephone"=>$this->telephone,
          "email"=>$this->email,
          "resAddress"=>$this->res_address,
          "lgaName"=>$this->getLGA->lga_name,
        ];
    }
}

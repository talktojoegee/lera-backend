<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementSetupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          "bank"=>$this->bank ?? 0,
          "newWaves"=>$this->newwaves ?? 0,
          "kgirs"=>$this->kgirs ?? 0,
          "lga"=>$this->lga ?? 0,
        ];
    }
}

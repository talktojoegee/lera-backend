<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncResource extends JsonResource
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
          "gis"=>$this->g_gis,
            "labs"=>$this->k_labs,
            "lastSync"=>date('d M, Y h:ia', strtotime($this->last_sync)),
            "lgaName"=>$this->getLGA->lga_name ?? 'All LGAs'
        ];
    }
}

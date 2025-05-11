<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          "sector"=>$this->sector ?? '',
          "users"=> $this->users ?? '',
          "review"=>$this->review ?? '',
          "verification"=>$this->verification ?? '',
          "authorization"=> $this->authorization ?? '',
          "approval"=> $this->approval ?? '',
        ];
    }
}

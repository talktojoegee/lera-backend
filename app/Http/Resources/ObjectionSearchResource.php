<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectionSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $statusLabel = null;
        switch ($this->status){
            case 0:
                $statusLabel = 'Pending';
                break;
            case 1:
                $statusLabel = 'Verified';
                break;
            case 2:
                $statusLabel = 'Declined';
                break;
            case 3:
                $statusLabel = 'Authorized';
                break;
            case 4:
                $statusLabel = 'Approved';
                break;
        }
        $objectionLabel = null;
        switch ($this->status){
            case 0:
                $objectionLabel = 'Pending';
                break;
            case 1:
                $objectionLabel = 'Verified';
                break;
            case 2:
                $objectionLabel = 'Declined';
                break;
            case 3:
                $objectionLabel = 'Authorized';
                break;
            case 4:
                $objectionLabel = 'Approved';
                break;
        }
        return [
            "billId"=>$this->billId,
            "buildingCode"=>$this->buildingCode ?? 'N/A',
            "billAmount"=> $this->billAmount ?? 'N/A',
            "amountPaid"=> $this->paidAmount ?? 'N/A',
            "rate"=> $this->rate ?? 0.0,
            "assessedValue"=> $this->assessed_value ?? 0.0,
            "status"=>$statusLabel,
            "statusInt"=>$this->status,
            "date"=>date('d M, Y h:ia', strtotime($this->date)),

            "assessmentNo"=>$this->assessment_no ?? 'N/A',
            "className"=>$this->className ?? 'N/A',
            "zone"=>$this->zone ?? 'N/A',

            "requestId"=>$this->requestId,
        ];
    }
}

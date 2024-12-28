<?php

namespace App\Http\Resources;

use App\Models\Billing;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $propertyCount = PropertyList::count();
        $billCount = Billing::count();
        $objectionCount = Billing::where("objection", 1)->count();
        $billAmount = Billing::sum("bill_amount");
        $paidAmount = Billing::sum("paid_amount");

        return [
            'noOfProperties'=>$propertyCount,
            'noOfBills'=>$billCount,
            'objections'=>$objectionCount,
            'billAmount'=>$billAmount,
            'amountPaid'=>$paidAmount
        ];
    }
}

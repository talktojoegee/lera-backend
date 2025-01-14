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
        $propertyCount = PropertyList::whereYear('created_at', $request->year)->count();
        $billCount = Billing::whereYear('created_at', $request->year)->count();
        $objectionCount = Billing::whereYear('created_at', $request->year)->where("objection", 1)->count();
        $billAmount = Billing::whereYear('created_at', $request->year)->sum("bill_amount");
        $paidAmount = Billing::whereYear('created_at', $request->year)->sum("paid_amount");

        return [
            'noOfProperties'=>$propertyCount,
            'noOfBills'=>$billCount,
            'objections'=>$objectionCount,
            'billAmount'=>number_format($billAmount ?? 0,2),
            'amountPaid'=>number_format($paidAmount ?? 0,2)
        ];
    }
}

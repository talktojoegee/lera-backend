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
        $billCount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->count();
        $billPendingCount = Billing::whereYear('entry_date', $request->year)->whereIn('status', [0,1,2,3])->count();
        $objectionCount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->where("objection", 1)->count();
        $billAmount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->sum("bill_amount");
        $paidAmount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->sum("paid_amount");

        return [
            'noOfProperties'=>$propertyCount,
            'noOfBills'=>$billCount,
            'noOfPendingBills'=>$billPendingCount,
            'objections'=>$objectionCount,
            'billAmount'=>number_format($billAmount ?? 0,2),
            'amountPaid'=>number_format($paidAmount ?? 0,2)
        ];
    }
}

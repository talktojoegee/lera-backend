<?php

namespace App\Http\Resources;

use App\Models\Billing;
use App\Models\Lga;
use App\Models\PropertyList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LGAChairDashboardStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $user = User::find($request->user);
        $lga = Lga::find($user->lga);

        $propertyCount = PropertyList::whereYear('created_at', $request->year)->where('lga_id', $user->lga_id)->count();
        $billCount = Billing::whereYear('entry_date', $request->year)->where('lga_id', $user->lga_id)->count();
        $objectionCount = Billing::whereYear('entry_date', $request->year)->where('lga_id', $user->lga_id)->where("objection", 1)->count();
        $billAmount = Billing::whereYear('entry_date', $request->year)->where('lga_id', $user->lga_id)->sum("bill_amount");
        $paidAmount = Billing::whereYear('entry_date', $request->year)->where('lga_id', $user->lga_id)->sum("paid_amount");

        return [
            'noOfProperties'=>$propertyCount ?? 0,
            'noOfBills'=>$billCount ?? 0,
            'objections'=>$objectionCount ?? 0,
            'billAmount'=>number_format($billAmount ?? 0,2),
            'amountPaid'=>number_format($paidAmount ?? 0,2),
            'lgaName'=>$lga->lga_name ?? '',
        ];
    }
}

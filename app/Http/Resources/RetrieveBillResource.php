<?php

namespace App\Http\Resources;

use App\Models\Billing;
use App\Models\Lga;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class RetrieveBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $year = $request->input('year');
        $lgaId = $request->input('lgaId');

        $results = DB::table('billings')
            ->select('lga_id AS lgaId', DB::raw('SUM(bill_amount) AS totalBillAmount'), DB::raw('COUNT(*) AS totalBills'))
            ->where('year', $year)
            ->where('lga_id', $lgaId)
            ->groupBy('lga_id')
            ->first();
        $lga = Lga::find($lgaId);
        $propertyListCount = PropertyList::where("lga_id", $lgaId)->count();
        $billsCount = Billing::where("lga_id", $lgaId)->where("year", $year)->count();

        return [
            'billAmount' => $results->totalBillAmount ?? 0,
            'lgaName' => $lga->lga_name ?? 'N/A',
            'noOfBuildings' => $propertyListCount ?? 0,
            'noOfBills' => $billsCount ?? 0
        ];

    }
}

<?php

namespace App\Http\Resources;

use App\Models\Billing;
use App\Models\Lga;
use App\Models\PropertyList;
use App\Models\User;
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
   /* public function toArray(Request $request): array
    {
        $year = $request->input('year');
        $lgaId = $request->input('lgaId');

        $results = DB::table('billings')
            ->select('lga_id AS lgaId', DB::raw('SUM(bill_amount) AS totalBillAmount'), DB::raw('COUNT(*) AS totalBills'))
            //->where('year', $year)
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

    }*/
    public function toArray(Request $request): array
    {
        $year = $request->input('year');

        // Query to fetch aggregate data for all LGAs
        $results = DB::table('billings')
            ->select('lga_id AS lgaId',
                DB::raw('SUM(bill_amount) AS totalBillAmount'),
                DB::raw('COUNT(*) AS totalBills'),
                'created_at AS entryDate',
                'billed_by'
            )
            ->when($year, function ($query, $year) {
                $query->where('year', $year);
            })
            ->groupBy('lga_id')
            ->get();

        // Iterate over results to prepare a detailed list
        $response = $results->map(function ($result) use ($year) {
            $lga = Lga::find($result->lgaId);
            $user = User::find($result->billed_by);
            $propertyListCount = PropertyList::where("lga_id", $result->lgaId)->count();
            $billsCount = Billing::where("lga_id", $result->lgaId)->where("year", $year)->count();

            return [
                'billAmount' => $result->totalBillAmount ?? 0,
                'lgaName' => $lga->lga_name ?? 'N/A',
                'noOfBuildings' => $propertyListCount ?? 0,
                'noOfBills' => $billsCount ?? 0,
                'entryDate'=>date('d M, Y h:ia', strtotime($result->entryDate)),
                'billedBy'=> !empty($user) ? $user->name : 'N/A'
            ];
        });

        return $response->toArray();
    }

}

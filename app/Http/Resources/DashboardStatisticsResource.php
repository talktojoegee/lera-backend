<?php

namespace App\Http\Resources;

use App\Models\Billing;
use App\Models\Lga;
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
        $year = (int) $request->year;
        $lgaIds = $request->input('allowedLgaIds', []);

        $propertyQuery = PropertyList::query();
        $billingQuery = Billing::query();

        if ($lgaIds !== []) {
            $propertyQuery->whereIn('lga_id', $lgaIds);
            $billingQuery->whereIn('lga_id', $lgaIds);
        }

        if ($year !== 0) {
            $propertyQuery->whereYear('created_at', $year);
            $billingQuery->whereYear('entry_date', $year);
        }

        $propertyCount = (clone $propertyQuery)->count();

        $billCount = (clone $billingQuery)
            ->where('status', 4)
            ->count();

        $billPendingCount = (clone $billingQuery)
            ->whereIn('status', [0, 1, 2, 3, 5])
            ->count();

        $objectionCount = (clone $billingQuery)
            ->where('status', 4)
            ->where('objection', 1)
            ->count();

        $billAmount = (clone $billingQuery)
            ->where('status', 4)
            ->sum('bill_amount');

        $paidAmount = (clone $billingQuery)
            ->where('status', 4)
            ->sum('paid_amount');

        $printedCounter = (clone $billingQuery)
            ->where('printed', 1)
            ->count();

        $deliveredCounter = (clone $billingQuery)
            ->where('delivered', 1)
            ->count();

        return [
            'noOfProperties' => $propertyCount,
            'noOfBills' => $billCount,
            'noOfPendingBills' => $billPendingCount,
            'objections' => $objectionCount,
            'billAmount' => number_format($billAmount ?? 0, 2),
            'amountPaid' => number_format($paidAmount ?? 0, 2),
            'printedCounter' => $printedCounter,
            'deliveredCounter' => $deliveredCounter,
            'byAgency' => $this->buildAgencyStats($year, $lgaIds),
        ];
    }

    /**
     * @param  array<int>  $lgaIds
     * @return array<int, array<string, mixed>>
     */
    private function buildAgencyStats(int $year, array $lgaIds): array
    {
        $lgasQuery = Lga::query()->orderBy('lga_name');
        if ($lgaIds !== []) {
            $lgasQuery->whereIn('id', $lgaIds);
        }

        $stats = [];
        foreach ($lgasQuery->get(['id', 'lga_name']) as $lga) {
            $propertyQuery = PropertyList::query()->where('lga_id', $lga->id);
            $billingQuery = Billing::query()->where('lga_id', $lga->id);

            if ($year !== 0) {
                $propertyQuery->whereYear('created_at', $year);
                $billingQuery->whereYear('entry_date', $year);
            }

            $agencyBillAmount = (clone $billingQuery)->where('status', 4)->sum('bill_amount');
            $agencyPaidAmount = (clone $billingQuery)->where('status', 4)->sum('paid_amount');

            $stats[] = [
                'lgaId' => $lga->id,
                'lgaName' => $lga->lga_name,
                'noOfProperties' => (clone $propertyQuery)->count(),
                'noOfBills' => (clone $billingQuery)->where('status', 4)->count(),
                'noOfPendingBills' => (clone $billingQuery)->whereIn('status', [0, 1, 2, 3, 5])->count(),
                'billAmount' => number_format($agencyBillAmount ?? 0, 2),
                'amountPaid' => number_format($agencyPaidAmount ?? 0, 2),
                'printedCounter' => (clone $billingQuery)->where('printed', 1)->count(),
                'deliveredCounter' => (clone $billingQuery)->where('delivered', 1)->count(),
            ];
        }

        return $stats;
    }

    /*public function toArray(Request $request): array
    {
        //return parent::toArray($request);
        $propertyCount = PropertyList::whereYear('created_at', $request->year)->count();
        $billCount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->count();
        $billPendingCount = Billing::whereYear('entry_date', $request->year)->whereIn('status', [0,1,2,3,5])->count();
        $objectionCount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->where("objection", 1)->count();
        $billAmount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->sum("bill_amount");
        $paidAmount = Billing::whereYear('entry_date', $request->year)->where('status', 4)->sum("paid_amount");
        $printedCounter = Billing::whereYear('entry_date', $request->year)->where('printed', 1)->count();
        $deliveredCounter = Billing::whereYear('entry_date', $request->year)->where('delivered', 1)->count();

        return [
            'noOfProperties'=>$propertyCount,
            'noOfBills'=>$billCount,
            'noOfPendingBills'=>$billPendingCount,
            'objections'=>$objectionCount,
            'billAmount'=>number_format($billAmount ?? 0,2),
            'amountPaid'=>number_format($paidAmount ?? 0,2),
            'printedCounter'=>$printedCounter,
            'deliveredCounter'=>$deliveredCounter,
        ];
    }*/
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Billing extends Model
{
    //
  public $currentYear;

  public function __construct(array $attributes = [])
  {
      $this->currentYear = Carbon::now()->year;
  }

    public function getBilledBy(){
        return $this->belongsTo(User::class, 'billed_by');
    }

    public function getLGA(){
        return $this->belongsTo(Lga::class, 'lga_id');
    }


    public function getPropertyList(){
        return $this->belongsTo(PropertyList::class, 'property_id');
    }

    public static function findBillSummaryByYearAndLgaId($year, $lgaId){
        return DB::table('billings')
            ->select('lga_id AS lgaId',
                DB::raw('SUM(bill_amount) AS totalBillAmount'),
                DB::raw('COUNT(*) AS totalBills'))
            //->where('year', $year)
            //->where('lga_id', $lgaId)
            ->groupBy('lga_id')
            ->get();
    }


    public static function getOutstandingBills(){
        return Billing::where('paid', 0)->where('objection', 0)->orderBy('id', 'DESC')->get();
    }

    public static function getPaidBills(){
        return Billing::where('paid', 1)->where('objection', 0)->orderBy('id', 'DESC')->get();
    }

    public static function getCurrentYearMonthlyBillAmount(){
        $currentYear = Carbon::now()->year;

        $monthlyBills = Billing::select(
            DB::raw('MONTH(entry_date) as month'),
            DB::raw('SUM(bill_amount) as total_bill_amount')
        )
            ->whereYear('entry_date', $currentYear)
            ->groupBy(DB::raw('MONTH(entry_date)'))
            ->orderBy(DB::raw('MONTH(entry_date)'))
            ->get();
        $billAmountsByMonth = array_fill(0, 12, 0);
        foreach ($monthlyBills as $bill) {
            $monthIndex = $bill->month - 1;
            $billAmountsByMonth[$monthIndex] = $bill->total_bill_amount;
        }
        return  $billAmountsByMonth;

    }

    public static function getCurrentYearMonthlyAmountPaid(){
        $currentYear = Carbon::now()->year;

        $monthlyBills = Billing::select(
            DB::raw('MONTH(entry_date) as month'),
            DB::raw('SUM(paid_amount) as total_bill_amount')
        )
            ->whereYear('entry_date', $currentYear)
            ->groupBy(DB::raw('MONTH(entry_date)'))
            ->orderBy(DB::raw('MONTH(entry_date)'))
            ->get();
        $billAmountsByMonth = array_fill(0, 12, 0);
        foreach ($monthlyBills as $bill) {
            $monthIndex = $bill->month - 1;
            $billAmountsByMonth[$monthIndex] = $bill->total_bill_amount;
        }
        return  $billAmountsByMonth;

    }
    public static function getCurrentYearBillsByZone(){
        $currentYear = Carbon::now()->year;

        return DB::table('billings')
            ->join('zones', 'billings.zone_name', '=', 'zones.zone_name')
            ->select('zones.zone_name', DB::raw('SUM(billings.bill_amount) as total_bills'))
            ->whereYear('billings.entry_date', $currentYear)
            ->groupBy('zones.zone_name')
            ->orderBy('zones.zone_name', 'ASC')
            ->get();

    }

}

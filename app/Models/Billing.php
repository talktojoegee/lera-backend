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
    public static function getBills($limit = 0, $skip = 0, $paid = 0, $objection = 0)
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    /*    if (!is_null($skip)) {
            $query->skip($skip);
        }

        if (!is_null($limit)) {
            $query->take($limit);
        }

        return $query->get();*/
    }
    public static function getBillsByParams($paid = 0, $objection = 0)
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->get();

    }


    /*public static function getOutstandingBills(){
        return Billing::where('paid', 0)
            ->where('objection', 0)
            ->orderBy('id', 'DESC')
            ->get();
    }*/


    public static function getPaidBills(){
        return Billing::where('paid', 1)->where('objection', 0)->orderBy('id', 'DESC')->get();
    }

    public static function getCurrentYearMonthlyBillAmount($year){


        $monthlyBills = Billing::select(
            DB::raw('MONTH(entry_date) as month'),
            DB::raw('SUM(bill_amount) as total_bill_amount')
        )
            ->whereYear('entry_date', $year)
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

    public static function getCurrentYearMonthlyAmountPaid($year){


        $monthlyBills = Billing::select(
            DB::raw('MONTH(entry_date) as month'),
            DB::raw('SUM(paid_amount) as total_bill_amount')
        )
            ->whereYear('entry_date', $year)
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
    public static function getCurrentYearBillsByZone($year){


        return DB::table('billings')
            ->join('zones', 'billings.zone_name', '=', 'zones.sub_zone')
            ->select('zones.sub_zone', DB::raw('SUM(billings.bill_amount) as total_bills'))
            ->whereYear('billings.entry_date', $year)
            ->groupBy('zones.sub_zone')
            ->orderBy('zones.sub_zone', 'ASC')
            ->get();

    }

    public static function getBillByYearLgaId($year, $lgaId){
        return Billing::where('lga_id', $lgaId)->where('year', $year)->first();
    }

}

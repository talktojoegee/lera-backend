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


    public function getPropertyClassification(){
        return $this->belongsTo(PropertyClassification::class, "class_id");
    }

    public function getChargeRate(){
        return $this->belongsTo(ChargeRate::class, "occupancy");
    }

    public function getObjection(){
        return $this->belongsTo(Objection::class, "bill_id");
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
    public static function getBills($limit = 0,
                                    $skip = 0,
                                    $paid = 0,
                                    $objection = 0,
                                    $status = 0,
                                    //$returned = 0,
    )
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->where('status', $status)
            //->orWhere('returned', $returned)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function getAllPaidBills($limit = 0,
                                    $skip = 0,
                                    $paid = 0,
                                    $objection = 0,
                                    $status = 0,
    )
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->where('status', $status)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function getBillsByStatus($limit = 0,
                                            $skip = 0,
                                            $status = 0
    )
    {
        return Billing::where('status', $status)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }


    public static function getAllPendingBillsByStatus($limit = 0,
                                            $skip = 0
    )
    {
        return Billing::whereIn('status', [0,1,2])
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }


    public static function getSpecialInterestBillsByStatus($limit = 0,
                                            $skip = 0,
                                            $status = 0
    )
    {
        return Billing::where('status', $status)
            ->where('special', 1)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }


    public static function getAllReturnedBills($limit = 0,
                                            $skip = 0,
    )
    {
        return Billing::where('returned', 1)
            ->where('special', 0)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function getAllSpecialInterestReturnedBills($limit = 0,
                                            $skip = 0,
    )
    {
        return Billing::where('returned', 1)
            ->where('special', 1)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function getAllBillsByParams($paid = 0, $objection = 0, $status=0)
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->where('status', $status)
            ->get();

    }

    public static function getBillsByParams($paid = 0, $objection = 0, $status=0)
    {
        return Billing::where('paid', $paid)
            ->where('objection', $objection)
            ->where('status', $status)
            //->orWhere('returned', $returned)
            ->get();

    }
    public static function getBillsByParamsByStatus($status = 0)
    {
        return Billing::where('status', $status)
            //->orWhere('returned', $returned)
            ->get();

    }

    public static function getAllPendingBillsByParamsByStatus()
    {
        return Billing::whereIn('status', [0,1,2])
            ->get();

    }

    public static function getSpecialInterestBillsByParamsByStatus($status = 0)
    {
        return Billing::where('status', $status)
            ->where('special', 1)
            ->get();

    }

     public static function getAllReturnedBillsByParams()
        {
            return Billing::where('returned', 1)
                ->where('special', 0)
                ->get();

        }

     public static function getAllSpecialInterestReturnedBillsByParams()
        {
            return Billing::where('returned', 1)
                ->where('special', 1)
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

    public static function getCurrentYearBillsByLGA($year){

        return DB::table('billings')
            ->join('lgas', 'billings.lga_id', '=', 'lgas.id')
            ->select('lgas.lga_name', DB::raw('SUM(billings.bill_amount) as total_bills'))
            ->whereYear('billings.entry_date', $year)
            ->groupBy('lgas.lga_name')
            ->orderBy('lgas.lga_name', 'ASC')
            ->get();


    }

    public static function getCurrentYearPaymentByLGA($year){

        return DB::table('billings')
            ->join('lgas', 'billings.lga_id', '=', 'lgas.id')
            ->select('lgas.lga_name', DB::raw('SUM(billings.paid_amount) as amount'))
            ->whereYear('billings.entry_date', $year)
            ->groupBy('lgas.lga_name')
            ->orderBy('lgas.lga_name', 'ASC')
            ->get();


    }

    public static function getBillByYearLgaId($year, $lgaId){
        return Billing::where('lga_id', $lgaId)->where('year', $year)->first();
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Billing extends Model
{
    //

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

}

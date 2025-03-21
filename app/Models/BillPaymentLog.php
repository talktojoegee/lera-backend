<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPaymentLog extends Model
{
    protected $fillable = [
        "bill_master",
        "paid_by",
        "amount",
        "trans_ref",
        "reference",
        "receipt_no",
        "payment_code",
        "assessment_no",
        "bank_name",
        "branch_name",
        "pay_mode",
        "customer_name",
        "email",
        "kgtin",
        "ward",
        "zone",
        "building_code",
        "lga_id",
        "entry_date",
        "token"
    ];


    public function lga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function getBill(){
        return $this->belongsTo(Billing::class, 'bill_master');
    }


    public static function getCustomerStatementByKgtinDate($buildingCode, $from, $to){
        return BillPaymentLog::where('building_code', $buildingCode)
            ->whereBetween('entry_date',[$from, $to])
            ->orderBy('entry_date', 'ASC')->get();
    }

    public static function getPaymentReportByLGADateRange($lga, $from, $to){
        return BillPaymentLog::when($lga > 0, function($query) use ($lga) {
            return $query->where('lga_id', $lga);
        })->whereBetween('entry_date',[$from, $to] )
            ->orderBy('id', 'ASC')
            ->get();
    }
    public static function getPaymentReportByWardDateRange($ward, $from, $to){
        return BillPaymentLog::where('ward', $ward)->whereBetween('entry_date',[$from, $to] )
            ->orderBy('id', 'ASC')
            ->get();
    }

    public static function getPaymentReportByZoneDateRange($zone, $from, $to){
        return BillPaymentLog::where('zone', $zone)->whereBetween('entry_date',[$from, $to] )
            ->orderBy('id', 'ASC')
            ->get();
    }
}

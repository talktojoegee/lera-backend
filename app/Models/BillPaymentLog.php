<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPaymentLog extends Model
{
    protected $fillable = [
        'bill_master',
        'paid_by',
        'amount',
        'trans_ref',
        'reference',
        'receipt_no',
        'payment_code',
        'assessment_no',
        'bank_name',
        'branch_name',
        'pay_mode',
        'customer_name',
        'email',
        'kgtin',
        'ward',
        'zone',
        'building_code',
        'lga_id',
        'entry_date',
        'token',
        'matched',
        'is_discount',
        'discount',
    ];

    public function lga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function getBill()
    {
        return $this->belongsTo(Billing::class, 'bill_master');
    }

    /**
     * @param  list<int>  $lgaIds
     */
    public static function getCustomerStatementByKgtinDate($buildingCode, $from, $to, $lgaIds = [])
    {
        return BillPaymentLog::where('building_code', $buildingCode)
            ->when(! empty($lgaIds), fn ($q) => $q->whereIn('lga_id', $lgaIds))
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date', 'ASC')->get();
    }

    /**
     * @param  list<int>  $lgaIds
     */
    public static function getPaymentReportByLGADateRange($lga, $from, $to, $lgaIds = [])
    {
        return BillPaymentLog::when($lga > 0, function ($query) use ($lga) {
            return $query->where('lga_id', $lga);
        })
            ->when(! empty($lgaIds), fn ($q) => $q->whereIn('lga_id', $lgaIds))
            ->where('is_discount', 0)->whereBetween('entry_date', [$from, $to])
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * @param  list<int>  $lgaIds
     */
    public static function getPaymentReportByWardDateRange($ward, $from, $to, $lgaIds = [])
    {
        return BillPaymentLog::where('ward', $ward)
            ->when(! empty($lgaIds), fn ($q) => $q->whereIn('lga_id', $lgaIds))
            ->where('is_discount', 0)->whereBetween('entry_date', [$from, $to])
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * @param  list<int>  $lgaIds
     */
    public static function getPaymentReportByZoneDateRange($zone, $from, $to, $lgaIds = [])
    {
        return BillPaymentLog::where('zone', $zone)
            ->when(! empty($lgaIds), fn ($q) => $q->whereIn('lga_id', $lgaIds))
            ->where('is_discount', 0)->whereBetween('entry_date', [$from, $to])
            ->orderBy('id', 'ASC')
            ->get();
    }
}

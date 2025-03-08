<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintBillLog extends Model
{
    protected $fillable = [
        'bill_id',
        'status',
        'user_id',
        'batch_code',
        'printed_by',
        'assessment_no'
    ];

    public function getUser(){
        return $this->belongsTo(User::class, 'user_id');
    }


    public static function fetchPrintBillLogByBatchCode($limit, $skip){
        return PrintBillLog::skip($skip)
            ->take($limit)->orderBy('id', "DESC")->groupBy('batch_code')->get();
    }

    public static function viewPrintBillLogByBatchCode($batchCode){
        return PrintBillLog::where('batch_code', $batchCode)->get();
    }


}

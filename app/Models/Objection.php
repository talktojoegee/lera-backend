<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Objection extends Model
{


    protected $fillable =
        [
         'bill_id',
        'submitted_by',
        'reason',
        'relief_ids',
        'status',
        'actioned_by',
        'date_actioned',
        'request_id',
        ];

    public function getSubmittedBy(){
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function getBill(){
        return $this->belongsTo(Billing::class, 'bill_id');
    }


    public static function fetchObjectionsByStatus($status, $limit, $skip){
        return Objection::where('status', $status)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function fetchObjectionByParam($status){
        return Objection::where('status', $status)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function getReliefs($ids){
        return Relief::whereIn('id', $ids)->pluck('item');
    }
}

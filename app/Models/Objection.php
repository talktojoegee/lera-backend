<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            'objection_id',
        ];

    public function getSubmittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function getBilledBy()
    {
        return $this->belongsTo(User::class, 'billed_by');
    }

    public function getVerifiedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function getAuthorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function getApprovedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getBill()
    {
        return $this->belongsTo(Billing::class, 'bill_id');
    }

    public function getObjectionAttachments()
    {
        return $this->hasMany(ObjectionAttachment::class, 'objection_id');
    }

    public static function fetchObjectionsByStatus($status, $limit, $skip)
    {
        return Objection::where('status', $status)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public static function fetchObjectionByParam($status)
    {
        return Objection::where('status', $status)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function getReliefs($ids)
    {
        return Relief::whereIn('id', $ids)->get('item');
    }

    /**
     * Search objection bills scoped to allowed LGAs.
     *
     * @param  list<int>  $lgaIds
     */
    public static function searchBills($keyword, $lgaIds, $status, $special)
    {
        return DB::table('billings')
            ->leftJoin('property_lists', 'billings.property_id', '=', 'property_lists.id')
            ->leftJoin('objections', 'objections.bill_id', '=', 'billings.id')
            ->leftJoin('property_classifications', 'property_lists.class_id', '=', 'property_classifications.id')
            ->select(
                'billings.id as billId',
                'billings.building_code as buildingCode',
                'billings.status',
                'billings.special',
                'billings.property_use',
                'billings.assessment_no',

                'property_lists.property_name',
                'property_lists.building_code',
                'property_classifications.class_name as className',
                'billings.assessment_no',
                'billings.pav_code',
                'billings.year',
                'billings.zone_name as zone',
                'billings.class_id',
                'billings.property_id',
                'billings.lga_id',
                'billings.bill_amount as billAmount',
                'billings.occupancy',
                'billings.paid_amount as amountPaid',
                'billings.assessed_value',
                'billings.special',
                'billings.status',
                'billings.url',
                'billings.property_id',
                'billings.bill_rate as rate',
                'objections.created_at as date',
                'objections.request_id as requestId',
            )
            ->where('objections.status', $status)
            ->when(! empty($lgaIds), fn ($q) => $q->whereIn('billings.lga_id', $lgaIds))
            ->where(function ($query) use ($keyword) {
                $query->where('billings.assessment_no', 'LIKE', "%{$keyword}%")
                    ->orWhere('billings.building_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.property_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('property_lists.building_code', 'LIKE', "%{$keyword}%")
                    ->orWhere('objections.request_id', 'LIKE', "%{$keyword}%");
            })
            ->distinct()
            ->get();
    }
}
/*
 *




assessedValue
lgaName
submittedBy
reason
year
reliefs

statusInt

billEntryDate
assessmentNo

age
pavCode
payStatus


objectionAssessValue
objectionRate
objectionAmount
objectionStatus
ownerName
ownerEmail
ownerMobileNo
ownerKgTin
actionedBy
dateActioned
authorizedBy
authorizedDate
approvedBy
approvedDate
attachments
 */

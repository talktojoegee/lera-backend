<?php

namespace App\Exports;

use App\Http\Resources\BillingExportResource;
use App\Http\Resources\OutstandingBillResource;
use App\Models\Billing;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BillingExport implements  FromCollection, WithHeadings
{
    public $type;
    public $userId;

    public function __construct($userId, $type){
        $this->type = $type;
        $this->userId = $userId;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        switch ($this->type){
            case 'normal-outstanding':
                return BillingExportResource::collection($this->getBills(4,0))->collection;
            case 'special-outstanding':
                return BillingExportResource::collection($this->getBills(4,1))->collection;
            case 'all-pending':
                return BillingExportResource::collection($this->getAllPendingBills(0))->collection;
            case 'returned-normal':
                return BillingExportResource::collection($this->getReturnedBills(0,1))->collection;
            case 'returned-special':
                return BillingExportResource::collection($this->getReturnedBills(1,1))->collection;
            case 'paid-bills':
                return BillingExportResource::collection($this->getPaidBills(4,1, 0))->collection;
            case 'si-paid-bills':
                return BillingExportResource::collection($this->getPaidBills(4,1, 1))->collection;
        }

    }
    public function headings(): array
    {
        return ['Assessment No', 'Building Code', 'Year', 'Billing Code',
            'Zone', 'Category', 'Occupancy', 'Charge Rate',
            'Assessed Mkt. Value(₦)', 'LUC(₦)', 'BA', 'RR', 'DR', 'LR', 'LA', 'CR', 'BR', 'Property Use', 'Property Name'];
    }
    public function getBills($status, $special){
        $user = User::find($this->userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return Billing::where('status', $status)
            ->whereIn('property_use', $propertyUse)
            ->where('special', $special)
            ->orderBy('id', 'DESC')
            ->get();
    }
    public function getAllPendingBills($status){
        $user = User::find($this->userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return Billing::where('status', $status)
            ->whereIn('property_use', $propertyUse)
            ->orderBy('id', 'DESC')
            ->get();
    }
    public function getReturnedBills($special, $returned){
        $user = User::find($this->userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return Billing::whereIn('property_use', $propertyUse)
            ->where('returned', $returned)
            ->where('special', $special)
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function getPaidBills($status, $paid, $special){
        $user = User::find($this->userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return Billing::whereIn('property_use', $propertyUse)
            ->where('status', $status)
            ->where('paid', $paid)
            ->where('special', $special)
            ->orderBy('id', 'DESC')
            ->get();
    }
}

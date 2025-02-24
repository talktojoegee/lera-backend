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
        }

    }
    public function headings(): array
    {
        return ['Assessment No', 'Building Code', 'Year', 'Billing Code',
            'Zone', 'Category', 'Occupancy', 'Charge Rate',
            'Assessed Mkt. Value(₦)', 'LUC(₦)', 'BA', 'RR', 'DR', 'LR', 'LA', 'CR', 'BR', 'Property Use'];
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
}

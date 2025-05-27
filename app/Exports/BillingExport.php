<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Contracts\Support\Responsable;

class BillingExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, Responsable
{
    public $type;
    public $userId;

    public function __construct($userId, $type)
    {
        $this->type = $type;
        $this->userId = $userId;
    }

    public function query()
    {
        $user = User::find($this->userId);

        if (!$user) {
            abort(404, 'Whoops! Something went wrong.');
        }

        $propertyUse = explode(',', $user->sector);

        switch ($this->type) {
            /*case 'normal-outstanding':
                return Billing::where('status', 4)->where('special', 0)->whereIn('property_use', $propertyUse)->orderByDesc('id');*/
            case 'special-outstanding':
                return Billing::where('status', 4)->where('special', 1)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'all-pending':
                return Billing::where('status', 0)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'returned-normal':
                return Billing::where('returned', 1)->where('special', 0)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'returned-special':
                return Billing::where('returned', 1)->where('special', 1)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'paid-bills':
                return Billing::where('status', 4)->where('paid', 1)->where('special', 0)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'si-paid-bills':
                return Billing::where('status', 4)->where('paid', 1)->where('special', 1)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            default:
                abort(400, 'Invalid export type.');
        }
    }

    public function map($bill): array
    {
        return [
            $bill->assessment_no,
            $bill->building_code,
            $bill->year,
            $bill->pav_code,
            $bill->zone_name,
            $bill->getPropertyClassification->class_name,
            $bill->getChargeRate->occupancy ?? '',
            $bill->cr,
            $bill->assessed_value ?? 0,
            $bill->bill_amount,
            $bill->ba,
            $bill->rr,
            $bill->dr,
            $bill->lr,
            $bill->la,
            $bill->cr,
            $bill->br,
            $bill->property_use,
            $bill->getPropertyList->property_name ?? '',
            $bill->return_reason ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'Assessment No', 'Building Code', 'Year', 'Billing Code',
            'Zone', 'Category', 'Occupancy', 'Charge Rate',
            'Assessed Mkt. Value', 'LUC', 'BA', 'RR',
            'DR', 'LR', 'LA', 'CR',
            'BR', 'Property Use', 'Property Name', 'Reason'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function toResponse($request)
    {
        // TODO: Implement toResponse() method.
    }
}

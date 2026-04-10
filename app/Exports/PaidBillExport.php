<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaidBillExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, Responsable
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

        $baseQuery = Billing::query()
            ->with([
                'getPropertyClassification:id,class_name',
                'getPropertyList:id,owner_name,owner_kgtin,property_name',
            ])
            ->select([
                'id', 'assessment_no', 'building_code', 'year', 'zone_name', 'class_id',
                'property_id', 'bill_amount', 'paid_amount', 'date_approved', 'status',
                'paid', 'special', 'objection', 'property_use'
            ]);

        switch ($this->type) {
            case 'paid-bills':
                return $baseQuery->where('status', 4)->where('paid', 1)->where('special', 0)->where('objection',0)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'partly-paid-bills':
                return
                    $baseQuery->where('paid_amount', '>', 0)
                        ->where('objection', 0)
                        ->whereIn('property_use', $propertyUse)
                        ->where('paid', 0)
                        ->where('special', 0)
                        ->where('status', 4)
                        ->orderByDesc('id');
            case 'normal-outstanding':
                return $baseQuery->where('paid', 0)
                    ->where('objection', 0)
                    ->where('status', 4)
                    ->whereIn('special', [0])
                    ->whereIn('property_use', $propertyUse)
                    ->orderByDesc('id');
            default:
                abort(400, 'Invalid export type.');
        }
    }

    public function map($bill): array
    {

        if($this->type == 'normal-outstanding'){
            return [
                date('d/m/Y', strtotime($bill->date_approved)),
                $bill->assessment_no,
                $bill->building_code,
                $bill->year,
                $bill->zone_name ?? '',
                $bill->getPropertyClassification->class_name ?? '',
                $bill->getPropertyList->owner_name ?? '',
                $bill->getPropertyList->property_name ?? '',
                $bill->bill_amount ?? 0,
                $bill->paid_amount ?? 0,
                ($bill->bill_amount - $bill->paid_amount),
            ];
        }
        return [
            $bill->assessment_no,
            $bill->building_code,
            $bill->year,
            $bill->zone_name ?? '',
            $bill->getPropertyClassification->class_name ?? '',
            $bill->getPropertyList->owner_name ?? '',
            $bill->getPropertyList->owner_kgtin ?? '',
            $bill->bill_amount ?? 0,
            $bill->paid_amount ?? 0,
            ($bill->bill_amount - $bill->paid_amount),
        ];
    }

    public function headings(): array
    {
        if ($this->type == 'normal-outstanding') {
            return [
                'Approval Date','Assessment No.', 'Building Code', 'Year',
                'Zone', 'Category', 'Owner','Property Name', 'Bill Amount',
                'Payment', 'Balance'
            ];
        }
        return [
            'Assessment No', 'Building Code', 'Year',
            'Zone', 'Category', 'Owner', 'KGTIN', 'Bill Amount',
            'Payment', 'Balance'
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function toResponse($request)
    {
        // TODO: Implement toResponse() method.
    }
}

<?php

namespace App\Exports;

use App\Models\Billing;
use App\Traits\ScopesBillsByUserSector;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BillingExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    use ScopesBillsByUserSector;

    public $type;

    public $userId;

    public function __construct($userId, $type)
    {
        $this->type = $type;
        $this->userId = (int) $userId;
    }

    public function query()
    {
        $baseQuery = Billing::query()
            ->with([
                'getChargeRate:id,occupancy',
                'getPropertyList:id,property_name',
            ])
            ->select([
                'id', 'assessment_no', 'building_code', 'year', 'pav_code', 'zone_name',
                'class_id', 'occupancy', 'cr', 'assessed_value', 'bill_amount', 'ba',
                'rr', 'dr', 'lr', 'la', 'br', 'property_use', 'property_id', 'return_reason',
                'status', 'special', 'paid', 'returned', 'lga_id',
            ]);

        $baseQuery = $this->applySectorScope($baseQuery, $this->userId);

        return match ($this->type) {
            'special-outstanding' => $baseQuery->where('status', 4)->where('special', 1)->orderByDesc('id'),
            'returned-normal' => $baseQuery->where('returned', 1)->where('special', 0)->orderByDesc('id'),
            'returned-special' => $baseQuery->where('returned', 1)->where('special', 1)->orderByDesc('id'),
            'si-paid-bills' => $baseQuery->where('status', 4)->where('paid', 1)->where('special', 1)->orderByDesc('id'),
            default => throw new \InvalidArgumentException('Invalid export type: '.$this->type),
        };
    }

    public function map($bill): array
    {
        return [
            $bill->assessment_no,
            $bill->building_code,
            $bill->year,
            $bill->pav_code,
            $bill->zone_name ?? '',
            $bill->property_use ?? '',
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
            'BR', 'Property Use', 'Property Name', 'Reason',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

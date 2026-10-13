<?php

namespace App\Exports;

use App\Models\Billing;
use App\Traits\ScopesBillsByUserSector;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PendingBillExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    use ScopesBillsByUserSector;

    public function __construct(
        public int $userId,
        public string $type = 'all-pending',
    ) {}

    public function query()
    {
        $baseQuery = Billing::query()
            ->with([
                'getPropertyList:id,property_name,owner_name',
                'getLGA:id,lga_name',
            ])
            ->select([
                'id', 'assessment_no', 'building_code', 'year', 'zone_name', 'property_use',
                'property_id', 'bill_amount', 'paid_amount', 'status', 'lga_id', 'date_approved',
                'entry_date', 'special',
            ]);

        $baseQuery = $this->applySectorScope($baseQuery, $this->userId);

        return match ($this->type) {
            'all-pending', 'pending-bills' => $baseQuery
                ->whereIn('status', [0, 1, 2, 3, 5])
                ->orderByDesc('id'),
            default => throw new \InvalidArgumentException('Invalid pending export type: '.$this->type),
        };
    }

    public function map($bill): array
    {
        $paidAmount = (float) ($bill->paid_amount ?? 0);

        return [
            $bill->date_approved ? date('d/m/Y', strtotime($bill->date_approved)) : '',
            $bill->assessment_no,
            $bill->building_code,
            $bill->year,
            $bill->zone_name ?? '',
            $bill->property_use ?? '',
            $bill->getPropertyList->property_name ?? '',
            $bill->getPropertyList->owner_name ?? '',
            $bill->bill_amount ?? 0,
            $paidAmount,
            max(0, (float) ($bill->bill_amount ?? 0) - $paidAmount),
            $bill->status,
            $bill->getLGA->lga_name ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'Approval Date', 'Assessment No.', 'Building Code', 'Year',
            'Zone', 'Category', 'Property Name', 'Owner',
            'Bill Amount', 'Paid Amount', 'Balance', 'Status', 'Agency',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

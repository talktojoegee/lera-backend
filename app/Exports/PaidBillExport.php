<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Traits\ScopesBillsByUserSector;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaidBillExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping
{
    use ScopesBillsByUserSector;

    public $type;

    public $userId;

    public function __construct($userId, $type)
    {
        $this->type = trim((string) $type);
        $this->userId = (int) $userId;
    }

    public function query()
    {
        $baseQuery = Billing::query()
            ->with([
                'getPropertyList:id,owner_name,owner_kgtin,property_name',
            ])
            ->select([
                'id', 'assessment_no', 'building_code', 'year', 'zone_name', 'class_id',
                'property_id', 'bill_amount', 'paid_amount', 'date_approved', 'date_paid', 'status',
                'paid', 'special', 'objection', 'property_use', 'lga_id', 'street_name',
            ])
            ->addSelect([
                'latest_payment_date' => BillPaymentLog::query()
                    ->select('entry_date')
                    ->whereColumn('bill_master', 'billings.id')
                    ->orderByDesc('entry_date')
                    ->limit(1),
            ]);

        $baseQuery = $this->applySectorScope($baseQuery, $this->userId);

        return match ($this->type) {
            'paid-bills' => $baseQuery
                ->where('paid', 1)
                ->where('objection', 0)
                ->where('status', 4)
                ->orderByDesc('id'),
            'partly-paid-bills' => $baseQuery
                ->where('paid_amount', '>', 0)
                ->where('objection', 0)
                ->where('paid', 0)
                ->orderByDesc('id'),
            'normal-outstanding' => $baseQuery
                ->where('paid', 0)
                ->where('objection', 0)
                ->where('status', 4)
                ->where('special', 0)
                ->orderByDesc('id'),
            default => throw new \InvalidArgumentException('Invalid export type: '.$this->type),
        };
    }

    public function map($bill): array
    {

        if ($this->type == 'normal-outstanding') {
            return [
                $bill->date_approved ? date('d/m/Y', strtotime($bill->date_approved)) : '',
                $bill->assessment_no,
                $bill->building_code,
                $bill->year,
                $bill->zone_name ?? '',
                $bill->property_use ?? '',
                $bill->getPropertyList->owner_name ?? '',
                $bill->getPropertyList->property_name ?? '',
                $bill->bill_amount ?? 0,
                $bill->paid_amount ?? 0,
                ($bill->bill_amount - $bill->paid_amount),
                $bill->getLGA->lga_name ?? '',
                $bill->street_name ?? '',
            ];
        }

        $row = [
            $bill->assessment_no,
            $bill->building_code,
            $bill->year,
            $bill->zone_name ?? '',
            $bill->property_use ?? '',
            $bill->getPropertyList->owner_name ?? '',
            $bill->getPropertyList->owner_kgtin ?? '',
            $bill->bill_amount ?? 0,
            $bill->paid_amount ?? 0,
            ($bill->bill_amount - $bill->paid_amount),
        ];

        if ($this->type === 'paid-bills') {
            array_splice($row, 1, 0, [$this->formatDatePaid($bill)]);
        }

        return $row;
    }

    public function headings(): array
    {
        if ($this->type == 'normal-outstanding') {
            return [
                'Approval Date', 'Assessment No.', 'Building Code', 'Year',
                'Zone', 'Category', 'Owner', 'Property Name', 'Amount',
                'Payment', 'Balance', 'Agency', 'Address',
            ];
        }

        $headings = [
            'Assessment No', 'Building Code', 'Year',
            'Zone', 'Category', 'Owner', 'KGTIN', 'Bill Amount',
            'Payment', 'Balance',
        ];

        if ($this->type === 'paid-bills') {
            array_splice($headings, 1, 0, ['Date Paid']);
        }

        return $headings;
    }

    protected function formatDatePaid($bill): string
    {
        $date = $bill->date_paid ?: $bill->latest_payment_date;
        if (! $date) {
            return '';
        }

        $timestamp = strtotime((string) $date);

        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }

    public function chunkSize(): int
    {
        return 500;
    }
}

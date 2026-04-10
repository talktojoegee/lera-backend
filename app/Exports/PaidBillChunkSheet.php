<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PaidBillChunkSheet implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize
{
    protected $type;
    protected $userId;
    protected $offset;
    protected $limit;

    public function __construct($type, $userId, $offset, $limit)
    {
        $this->type = $type;
        $this->userId = $userId;
        $this->offset = $offset;
        $this->limit = $limit;
    }

    public function query()
    {
        $user = User::find($this->userId);
        $propertyUse = explode(',', $user->sector);

        $query = Billing::query();

        switch ($this->type) {
            case 'paid-bills':
                $query->where('status', 4)->where('paid', 1)->where('special', 0)->where('objection', 0);
                break;
            case 'partly-paid-bills':
                $query->where('paid_amount', '>', 0)
                    ->where('paid', 0)
                    ->where('special', 0)
                    ->where('status', 4)
                    ->where('objection', 0);
                break;
            case 'normal-outstanding':
                $query->where('paid', 0)
                    ->where('objection', 0)
                    ->where('status', 4)
                    ->where('special', 0);
                break;
        }

        return $query->whereIn('property_use', $propertyUse)
            ->orderByDesc('id')
            ->skip($this->offset)
            ->take($this->limit);
    }

    public function map($bill): array
    {
        if ($this->type == 'normal-outstanding') {
            return [
                date('d/m/Y', strtotime($bill->date_approved)),
                $bill->assessment_no,
                $bill->building_code,
                $bill->year,
                $bill->zone_name,
                $bill->getPropertyClassification->class_name,
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
            $bill->zone_name,
            $bill->getPropertyClassification->class_name,
            $bill->getPropertyList->owner_name ?? '',
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
            'Zone', 'Category', 'Owner', 'Bill Amount',
            'Payment', 'Balance'
        ];
    }
}

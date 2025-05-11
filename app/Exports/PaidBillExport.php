<?php

namespace App\Exports;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Maatwebsite\Excel\Concerns\FromCollection;
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

        switch ($this->type) {
            case 'paid-bills':
                return Billing::where('status', 4)->where('paid', 1)->where('special', 0)->where('objection',0)->whereIn('property_use', $propertyUse)->orderByDesc('id');
            case 'partly-paid-bills':
                return
                    Billing::where('paid_amount', '>', 0)
                        ->where('objection', 0)
                        ->whereIn('property_use', $propertyUse)
                        ->where('paid', 0)
                        ->where('special', 0)
                        ->where('status', 4)
                        ->orderByDesc('id');
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
        return [
            'Assessment No', 'Building Code', 'Year',
            'Zone', 'Category', 'Owner', 'Bill Amount',
            'Payment', 'Balance'
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

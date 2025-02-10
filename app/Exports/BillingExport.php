<?php

namespace App\Exports;

use App\Http\Resources\BillingExportResource;
use App\Http\Resources\OutstandingBillResource;
use App\Models\Billing;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BillingExport implements  FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return BillingExportResource::collection(Billing::all())->collection;
    }
    public function headings(): array
    {
        return ['Assessment No', 'Building Code', 'Year', 'Billing Code',
            'Zone', 'Category', 'Occupancy', 'Charge Rate',
            'Assessed Mkt. Value(₦)', 'LUC(₦)'];
    }
}

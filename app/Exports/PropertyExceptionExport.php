<?php

namespace App\Exports;

use App\Http\Resources\PropertyExceptionExportResource;
use App\Http\Resources\PropertySearchResource;
use App\Models\PropertyException;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PropertyExceptionExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return PropertyExceptionExportResource::collection(PropertyException::all())->collection;
    }


    public function headings(): array
    {
        return ['Building Code', 'Owner', 'Billing Code',
            'Zone', 'LGA', 'Class', 'Property Use',
            'Occupancy', 'Property Name', 'Status', 'Reason'];
    }
}

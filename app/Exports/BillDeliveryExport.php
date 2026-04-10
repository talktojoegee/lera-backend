<?php

namespace App\Exports;

use App\Models\Lga;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BillDeliveryExport implements  FromCollection, WithHeadings, WithStyles
{
    protected $data;
    public $lga;
    public $from;
    public $to;
    public $lgaName = null;


    public function __construct($data, $lga, $from, $to){
        $this->data = $data;
        $this->lga = $lga;
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data);

    }

    public function headings(): array
    {
        $from = date('d/m/Y', strtotime($this->from));
        $to = date('d/m/Y', strtotime($this->to));
        if($this->lga == 0){
            $this->lgaName = "All LGA";
        }else{

            $this->lgaName = Lga::find($this->lga)->lga_name ?? '';
        }

        return [
            ['KOGI STATE GOVERNMENT'],
            ['CENTRAL BILLING SYSTEM'],
            ['NO. 1 BEACH ROAD, LOKOJA, KOGI STATE, NIGERIA.'],
            ['PHONE: 08083427276'],
            ['LAND USE CHARGE ASSESSMENT'],
            ['DEMAND NOTICE'],
            [''],
            ['LGA:', "$this->lgaName"],
            ['Bill Delivery report From:', "$from", 'to', "$to"],
            [''],
            ['#', 'DATE', 'ASSESSMENT NO.', 'BUILDING CODE', 'YEAR', 'BILLING CODE', 'ZONE', 'CATEGORY', 'PROPERTY USE',
        'OCCUPANCY','PROPERTY NAME','CHARGE RATE', 'ASSESSED MKT. VALUE', '(₦)LUC'],
        ];
    }
    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true, 'size' => 14]],
            7    => ['font' => ['bold' => true]],
        ];
    }
}

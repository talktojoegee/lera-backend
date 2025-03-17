<?php

namespace App\Exports;

use App\Http\Resources\CustomerStatementResource;
use App\Models\BillPaymentLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CustomerReportExport implements  FromCollection, WithHeadings, WithStyles
{


    protected $data;
    public $buildingCode;
    public $from;
    public $to;


    public function __construct($data, $buildingCode, $from, $to){
        $this->data = $data;
        $this->buildingCode = $buildingCode;
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

        return [
            ['KOGI STATE GOVERNMENT'],
            ['CENTRAL BILLING SYSTEM'],
            ['NO. 1 BEACH ROAD, LOKOJA, KOGI STATE, NIGERIA.'],
            ['PHONE: 08083427276'],
            ['LAND USE CHARGE ASSESSMENT'],
            ['DEMAND NOTICE'],
            [''],
            ['Payer ID:', ''],
            ['Building Code:', "$this->buildingCode"],
            //['Address:', ''],
            ['Customer Statement report From:', "$from", 'to', "$to"],
            [''],
            ['#', 'DATE', '(₦)AMOUNT', 'CHANNEL', 'RECEIPT', 'NARRATION'],
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

<?php

namespace App\Exports;

use App\Models\Lga;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use function PHPUnit\Framework\isNumeric;

class PaymentReportExport implements  FromCollection, WithHeadings, WithStyles
{

    protected $data;
    public $from;
    public $to;
    public $type;
    public $keyword;
    public $sub;

    public function __construct($data, $from, $to, $type, $keyword){
        $this->data = $data;
        $this->from = $from;
        $this->to = $to;
        $this->type = $type;
        $this->keyword = $keyword;
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
        $type = strtoupper($this->type);

        if(is_numeric($this->keyword)){
            if($this->keyword == 0){
                $this->sub = "All LGA";
            }else{
                $lga = Lga::find($this->keyword);
                $this->sub = $lga->lga_name ?? '';
            }
        }else{
            $this->sub = $this->keyword;
        }
        return [
            ['KOGI STATE GOVERNMENT'],
            ['CENTRAL BILLING SYSTEM'],
            ['NO. 1 BEACH ROAD, LOKOJA, KOGI STATE, NIGERIA.'],
            ['PHONE: 08083427276'],
            ['LAND USE CHARGE ASSESSMENT'],
            ['DEMAND NOTICE'],
            ['Type', "$type: ($this->sub) "],
            ['Payment Report From:', "$from", 'to', "$to"],
            [''],
            ['#', 'DATE', 'BUILDING CODE', 'ASSESSMENT NO.', 'RECEIPT NO.', 'TRANS. REF', 'OWNER NAME', 'LGA', '(NGN)AMOUNT'],
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

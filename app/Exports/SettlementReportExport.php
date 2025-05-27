<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SettlementReportExport implements FromView
{

    public $report;
    public $kgirs;
    public $lga;
    public $newwaves;
    public $from;
    public $to;
    public $lgaList;

    public function __construct($report, $lgaList, $kgirs, $lga, $newwaves, $from, $to)
    {
        $this->report = $report;
        $this->kgirs = $kgirs;
        $this->lga = $lga;
        $this->newwaves = $newwaves;
        $this->from = $from;
        $this->to = $to;
        $this->lgaList = $lgaList;
    }



    public function view(): View
    {
        return view('settlement-report', [
            'report' => $this->report,
            'kgirs' => $this->kgirs,
            'lga' => $this->lga,
            'newwaves' => $this->newwaves,
            'from' => $this->from,
            'to' => $this->to,
            'lgaList' => $this->lgaList,
        ]);
    }
}

<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;

class ReconciliationReportExport implements FromView
{
    public $report;

    public function __construct($report)
    {
        $this->report = $report;
    }


    public function view(): View
    {
        return view('reconciliation-report', [
            'report' => $this->report,
        ]);
    }
}

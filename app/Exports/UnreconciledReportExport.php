<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class UnreconciledReportExport implements FromView
{
    public $report;

    public function __construct($report)
    {
        $this->report = $report;
    }


    public function view(): View
    {
        return view('unreconciled-report', [
            'report' => $this->report,
        ]);
    }

}

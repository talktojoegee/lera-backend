<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PerformanceReportExport implements FromView
{
    protected $from, $to;
    protected $report;

    public function __construct($from, $to, $report)
    {
        $this->from = $from;
        $this->to = $to;
        $this->report = $report;
    }

    public function view(): View
    {
        return view('performance-report', [
            'from' => $this->from,
            'to' => $this->to,
            'reportData' => $this->report,
        ]);
    }


}

<?php

// app/Exports/WorkflowReportExport.php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class WorkflowReportExport implements FromView
{
    protected $year;
    protected $report;

    public function __construct($year, $report)
    {
        $this->year = $year;
        $this->report = $report;
    }

    public function view(): View
    {
        return view('workflow-report', [
            'year' => $this->year,
            'reportData' => $this->report,
        ]);
    }
}

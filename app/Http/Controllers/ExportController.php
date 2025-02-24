<?php

namespace App\Http\Controllers;

use App\Exports\BillingExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class ExportController extends Controller
{
    public function exportExcel(Request $request)
    {
        $type = $request->type ;
        $userId = $request->user ;
        return Excel::download(new BillingExport($userId,$type), 'billings.xlsx');
    }

}

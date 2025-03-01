<?php

namespace App\Http\Controllers;

use App\Exports\BillingExport;
use App\Jobs\ExportBillingJob;
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
/*
    public function exportExcel(Request $request)
    {
        $type = $request->type;
        $userId = $request->user;

        ExportBillingJob::dispatch($userId, $type);

        return response()->json([
            'message' => 'Your export is being processed. You will receive a notification when it is ready.'
        ]);
    }*/

}

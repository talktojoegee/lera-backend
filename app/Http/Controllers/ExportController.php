<?php

namespace App\Http\Controllers;

use App\Exports\BillingExport;
use App\Exports\CustomerReportExport;
use App\Exports\PaymentReportExport;
use App\Http\Resources\CustomerStatementResource;
use App\Jobs\ExportBillingJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;


class ExportController extends Controller
{
    public function exportExcel(Request $request)
    {


        $type = $request->type ;
        $userId = $request->user ;
        return Excel::download(new BillingExport($userId,$type), 'billings.xlsx');
    }

    public function exportCustomerReport(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'buildingCode'=>'required',
            'from'=>'required',
            'to'=>'required'
        ],[
            'buildingCode.required'=>"Building code is required" ,
            'from.required'=>"Set a start date" ,
            'to.required'=>"Set an end date"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        $buildingCode = $request->buildingCode ;
        $from = $request->from ;
        $to = $request->to ;
        $bills =  BillPaymentLog::getCustomerStatementByKgtinDate($buildingCode, $from, $to);
        $formattedData = [];
        foreach ($bills as $index => $billing) {
            $bill = Billing::find($billing->bill_master);
            $narration = "N/A";
            if(!empty($bill)){
                $pDate = date('d M, Y', strtotime($billing->entry_date));
                $narration = "LUC: {$bill->bill_amount}, Payment Date: {$pDate}, BUEAREU OF LANDS, Mode of Payment: {$billing->pay_mode},
            Assessment No.: {$bill->assessment_no}, Transaction Reference: {$bill->trans_ref}, Year: {$bill->year}";
            }
            $formattedData[] = [
                '#' => $index + 1,
                'DATE' => date('d M, Y', strtotime($billing->entry_date)),
                '(₦)AMOUNT' => number_format($billing->amount, 2),
                'CHANNEL' => $billing->pay_mode,
                'RECEIPT' => $billing->receipt_no,
                'NARRATION' => $narration,
            ];
        }


        return Excel::download(new CustomerReportExport($formattedData, $buildingCode, $from, $to), 'customer-report.xlsx');
    }


    public function exportPaymentReport(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'keyword'=>'required',
            'type'=>'required',
            'from'=>'required',
            'to'=>'required'
        ],[
            'keyword.required'=>"Something is missing" ,
            'from.required'=>"Set a start date" ,
            'to.required'=>"Set an end date",
            'type.required'=>"Indicate type",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $from = $request->from ;
        $to = $request->to ;
        $type = $request->type ;
        $keyword = $request->keyword ; //LGA ID ||
        switch ($type){
            case 'lga':
                $bills =  BillPaymentLog::getPaymentReportByLGADateRange($keyword, $from, $to);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }
                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
            case 'ward':
                $bills =  BillPaymentLog::getPaymentReportByWardDateRange($keyword, $from, $to);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    //$lga = Lga::find($);
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no ?? '',
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }
                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
            case 'zone':
                $bills =  BillPaymentLog::getPaymentReportByZoneDateRange($keyword, $from, $to);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }
                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
        }
    }

}

<?php

namespace App\Http\Controllers;

use App\Exports\BillingExport;
use App\Exports\CustomerReportExport;
use App\Exports\PaidBillExport;
use App\Exports\PaymentReportExport;
use App\Exports\PropertyExceptionExport;
use App\Exports\ReconciliationReportExport;
use App\Exports\SettlementReportExport;
use App\Http\Resources\CustomerStatementResource;
use App\Http\Resources\LGAResource;
use App\Http\Resources\SettlementReportResource;
use App\Jobs\ExportBillingJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\Lga;
use App\Models\Reconciliation;
use App\Models\ReconciliationMaster;
use App\Models\SettlementReportSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;


class ExportController extends Controller
{
    public function exportExcel(Request $request)
    {


        $type = $request->type ;
        $userId = $request->user ;
        if($type == 'normal-outstanding'){
            return Excel::download(new PaidBillExport($userId,$type), 'billings.xlsx');
        }
        return Excel::download(new BillingExport($userId,$type), 'billings.xlsx');
    }

    public function exportPaidBills(Request $request)
    {


        $type = $request->type ;
        $userId = $request->user ;
        return Excel::download(new PaidBillExport($userId,$type), 'billings.xlsx');
    }

    public function exportPropertyException(Request $request)
    {
        return Excel::download(new PropertyExceptionExport(), 'property-exceptions.xlsx');
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
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
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
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
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
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }
                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
        }
    }


    public function exportSettlementReport(Request $request){
        $validator = Validator::make($request->all(), [
            "from" => "required|date",
            "to" => "required|date"
        ], [
            "from.required" => "Choose start date",
            "to.required" => "Choose end date"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages(),
                "message"=>"Validation error",
                "detail"=>"All fields are required."
            ], 422);
        }
        $from = $request->from;
        $to = $request->to;
        $data = DB::table('bill_payment_logs')
            ->join('lgas', 'lgas.id', '=', 'bill_payment_logs.lga_id')
            ->orderBy('lgas.id', 'ASC')
            ->whereBetween('entry_date', [$from, $to])
            ->select('entry_date as date', 'building_code as buildingCode', 'assessment_no as assessmentNo', 'amount', 'lgas.lga_name as lgaName')
            ->get();
        $setup = SettlementReportSetup::first();
        $newWaves = isset($setup->newwaves) ? $setup->newwaves : 0;
        $lga = isset($setup->lga) ? $setup->lga : 0;
        $kgirs = isset($setup->kgirs) ? $setup->kgirs : 0;
        $lgaList = Lga::fetchAllLGAs();

        return Excel::download(new SettlementReportExport($data,$lgaList, $kgirs, $lga, $newWaves, $from, $to), 'settlement_report.xlsx');
    }

    public function exportReconciliationReport(Request $request){
        $validator = Validator::make($request->all(), [
            "uuid" => "required",
        ], [
            "uuid.required" => "Field is required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages(),
                "message"=>"Validation error",
                "detail"=>"All fields are required."
            ], 422);
        }
        $uuid = $request->uuid;
        $record = ReconciliationMaster::where("uuid", $uuid)->first();
        if(empty($record)){
            return response()->json([
                "errors" => "Whoops!",
                "message"=>"Something went wrong.",
                "detail"=>"No record found."
            ], 422);
        }
        $details = Reconciliation::where('master_id', $record->id)->get();

        return Excel::download(new ReconciliationReportExport($details), 'reconciliation_report.xlsx');
    }

}

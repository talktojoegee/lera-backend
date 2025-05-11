<?php

namespace App\Http\Controllers;

use App\Exports\WorkflowReportExport;
use App\Http\Resources\BillDetailExtractResource;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\CustomerStatementResource;
use App\Http\Resources\OutstandingBillResource;
use App\Http\Resources\PaymentReportResource;
use App\Http\Resources\WorkflowResource;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function handleCustomerStatementReport(Request $request){
        $validator = Validator::make($request->all(),
            [
                "buildingCode"=>"required",
                "from"=>"required|date",
                "to"=>"required|date",
                "requestedBy"=>"required",
            ],
            [
                "buildingCode.required"=>"Provide a valid Building Code",
                "from.required"=>"Choose a start period",
                "from.date"=>"Enter a valid date format",
                "to.required"=>"Choose an end period",
                "to.date"=>"Enter a valid date format",
                "requestedBy.required"=>"",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "message"=>"Validation failed!",
                "detail"=>"One or more required field is missing",
                "errors"=>$validator->messages()
            ],422);
        }

        $bills = BillPaymentLog::getCustomerStatementByKgtinDate($request->buildingCode, $request->from, $request->to);
        return response()->json([
           "bill"=>new BillDetailExtractResource($bills->first()),
            "data"=>CustomerStatementResource::collection($bills),
        ],200);
    }


    public function handlePaymentReportGeneration(Request $request){
        $validator = Validator::make($request->all(),
            [
                "buildingCode"=>"required",
                "from"=>"required|date",
                "to"=>"required|date",
                "requestedBy"=>"required",
            ],
            [
                "buildingCode.required"=>"Provide a valid Building Code",
                "from.required"=>"Choose a start period",
                "from.date"=>"Enter a valid date format",
                "to.required"=>"Choose an end period",
                "to.date"=>"Enter a valid date format",
                "requestedBy.required"=>"",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "message"=>"Validation failed!",
                "detail"=>"One or more required field is missing",
                "errors"=>$validator->messages()
            ],422);
        }

    }



    public function showPaymentReportPrint(Request $request){
        $validator = Validator::make($request->all(),
            [
                "keyword"=>"required",
                "from"=>"required|date",
                "to"=>"required|date",
                "user"=>"required",
                "type"=>"required",
            ],
            [
                "keyword.required"=>"Value is missing",
                "from.required"=>"Choose a start date",
                "from.date"=>"Enter a valid date format",
                "to.required"=>"Choose end date",
                "to.date"=>"Enter a valid date format",
                "type.required"=>"Indicate section",
                "user.required"=>"",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "message"=>"Validation failed!",
                "detail"=>"One or more required field is missing",
                "errors"=>$validator->messages()
            ],422);
        }
        $keyword = $request->keyword; //int
        $type = $request->type;
        $from = $request->from;
        $to = $request->to;
        switch ($type){
            case 'lga':
                $bills = BillPaymentLog::getPaymentReportByLGADateRange($keyword, $from, $to);
                return response()->json([
                    "data"=>PaymentReportResource::collection($bills),
                ],200);
            case 'zone':
                $bills =  BillPaymentLog::getPaymentReportByZoneDateRange($keyword, $from, $to);
                return response()->json(['data'=>PaymentReportResource::collection($bills)],200);
            case 'ward':
                $bills =  BillPaymentLog::getPaymentReportByWardDateRange($keyword, $from, $to) ;
                return response()->json(['data'=>PaymentReportResource::collection($bills)],200);
        }

    }



    public function workflowReport(Request $request){
        $validator = Validator::make($request->all(),
            [
                "year"=>"required"
            ],
            [
                "year.required"=>"Choose year",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "message"=>"Validation failed!",
                "detail"=>"One or more required field is missing",
                "errors"=>$validator->messages()
            ],422);
        }
        $bills = Billing::generateWorkflowReport($request->year);
        return response()->json(['data'=>$bills],200);
    }

    public function exportWorkflowReport(Request $request)
    {
        $request->validate([
            'year' => 'required'
        ]);

        $year = $request->year;

        $report = Billing::generateWorkflowReport($year);

        return Excel::download(new WorkflowReportExport($year, $report), 'workflow_report.xlsx');
    }



}

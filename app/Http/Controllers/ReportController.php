<?php

namespace App\Http\Controllers;

use App\Exports\PerformanceReportExport;
use App\Exports\WorkflowReportExport;
use App\Http\Resources\BillDetailExtractResource;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\CustomerStatementResource;
use App\Http\Resources\OutstandingBillResource;
use App\Http\Resources\PaymentReportResource;
use App\Http\Resources\WorkflowResource;
use App\Imports\PropertyImport;
use App\Imports\ReconciliationReportImport;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

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


    public function performanceReport(Request $request){
        $validator = Validator::make($request->all(),
            [
                "from"=>"required",
                "to"=>"required"
            ],
            [
                "from.required"=>"Choose start date",
                "to.required"=>"Choose end date",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "message"=>"Validation failed!",
                "detail"=>"One or more required field is missing",
                "errors"=>$validator->messages()
            ],422);
        }
        $bills = Billing::generatePerformanceReportByUsers($request->from, $request->to);
        //$bills = Billing::generatePerformanceReport($request->from, $request->to);
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

    public function exportPerformanceReport(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $report = Billing::generatePerformanceReportByUsers($request->from, $request->to);

        return Excel::download(new PerformanceReportExport($request->from, $request->to, $report), 'performance_report.xlsx');
    }


    public function handleReconciliationRequest(Request $request){
        $validator = Validator::make($request->all(), [
            "attachment" => "required|mimes:xlsx,xls|max:2048",
            "auth" => "required",
            "header" => "required",
            "monthYear" => "required",
        ], [
            "attachment.required" => "Enter property name",
            "header.required" => "Header is required",
            "auth.required" => "",
            "monthYear.required" => "Indicate month and year",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $path = $request->file('attachment')->store('uploads', 'public');

        if($request->hasFile('attachment')){
            $headers = (new HeadingRowImport)->toArray($request->file('attachment'))[0][0];
            $headerList = array_values($headers);
            $cleanHeaders = array_filter($headerList, function ($header) {
                return !is_numeric($header);
            });
            $cleanHeaders = array_values($cleanHeaders);
            $preparedFormat = $this->getSheetHeaderFormat();
            $uploadedHeaders = array_map(fn($h) => strtolower(trim($h)), $headerList);
            try {

               // return response()->json(['data'=>$cleanHeaders],200);
                if ($cleanHeaders === $preparedFormat) {
                    DB::beginTransaction();
                    $fileName = $this->uploadFile($request);
                    Excel::import(new ReconciliationReportImport($request->header, $request->monthYear, $request->auth),
                        public_path("assets/drive/import/{$fileName}"));
                    DB::commit();
                    return response()->json(['message' => ' Processing request .']);
                }else{
                    return response()->json([
                        "errors" => "Something went wrong",
                        "message"=>"Validation error",
                        "detail"=>"Mis-matched fields in the uploaded file. Please the recommended template"
                    ], 422);
                }
            }catch (\Exception $e){
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()]);
            }

        }else{
            return response()->json([
                "errors" => "Choose a file to upload"
            ], 422);
        }


    }

    private function getSheetHeaderFormat(){
        return [
            'entrydate',
            'details',
            'valuedate',
            'debit',
            'credit',
            'balance'
        ];
    }

    private function uploadFile(Request $request)
    {
        if ($request->hasFile('attachment')) {
            $file = $request->attachment;
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '_' . date('Ymd') . '.' . $extension;
            $dir = 'assets/drive/import/';
            $file->move(public_path($dir), $filename);
            return $filename;

        }
    }

}

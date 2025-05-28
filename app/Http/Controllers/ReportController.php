<?php

namespace App\Http\Controllers;

use App\Exports\PerformanceReportExport;
use App\Exports\WorkflowReportExport;
use App\Http\Resources\BillDetailExtractResource;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\CustomerStatementResource;
use App\Http\Resources\OutstandingBillResource;
use App\Http\Resources\PaymentReportResource;
use App\Http\Resources\ReconciliationHistoryResource;
use App\Http\Resources\ReconciliationResource;
use App\Http\Resources\WorkflowResource;
use App\Imports\PropertyImport;
use App\Imports\ReconciliationReportImport;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\Reconciliation;
use App\Models\ReconciliationMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            try {
                if ($cleanHeaders === $preparedFormat) {
                    DB::beginTransaction();
                    $fileName = $this->uploadFile($request);
                    $master = ReconciliationMaster::create([
                        "user_id"=>$request->auth,
                        "month"=>date('m', strtotime($request->monthYear)),
                        "year"=>date('Y', strtotime($request->monthYear)),
                        "uuid"=>Str::uuid(),
                    ]);
                    Excel::import(new ReconciliationReportImport($request->header, $request->monthYear, $request->auth, $master->id),
                        public_path("assets/drive/import/{$fileName}"));
                    DB::commit();
                    $data = Reconciliation::where('month', date('m', strtotime($request->monthYear)))
                        ->where('year', date('Y', strtotime($request->monthYear)))
                        ->get();
                    return response()->json([
                        "data"=>ReconciliationResource::collection($data),
                        "uuid"=>$master->uuid ?? '',
                    ],200);
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

    public function showReconciliationHistory(){
        $record = ReconciliationMaster::orderBy('id', 'desc')->get();
        return response()->json(['data'=>ReconciliationHistoryResource::collection($record)],200);
    }


    public function showReconciliationHistoryDetail(Request $request){
        $uuid = $request->uuid;
        $record = ReconciliationMaster::where("uuid", $uuid)->first();
        if(empty($record)){
            return response()->json([
                "message"=>"Whoops!",
                "detail"=>"No record found",
                "errors"=>"Something went wrong"
            ],422);
        }
        $data = Reconciliation::where('master_id', $record->id)->get();
        return response()->json([
            "data"=>ReconciliationResource::collection($data),
        ],200);

    }

    public function reQueryConciliation(Request $request){
        $validator = Validator::make($request->all(), [
            "assessmentNo" => "required",
            "amount" => "required",
            "authUser" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $record = Reconciliation::where('assessment_no', $request->assessmentNo)->where("credit", $request->amount)
            ->where("reconciled", 0)
            ->first();
        if(empty($record)){
            return response()->json([
                "errors"=>"Whoops!",
            ],422);
        }
        //query payment
        $payment = $this->confirmReconciliation($request->authUser, $record->value_date, $record->assessment_no, $record->credit);
        if($payment){
            $record->reconciled = 1;
            $record->reason = "Match found!";
            $record->reconciled_by = $request->authUser;
            $record->date_reconciled = now();
            $record->save();
            return response()->json(["data"=>"Action successful"],200);
        }else{
            return response()->json([
                "errors" => "Whoops!",
                "message"=>"Something went wrong",
                "detail"=>"Could not reconcile & confirm record!"
            ],422);
        }

    }

    public function handleConfirmReconciliationRequest(Request $request){
        $validator = Validator::make($request->all(), [
            "uuid" => "required",
            "authUser" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $recordMaster = ReconciliationMaster::where("uuid", $request->uuid)->first();
        if(empty($recordMaster)){
            return response()->json([
                "errors"=>"Whoops! No record found!",
            ],422);
        }
        $recordDetails = Reconciliation::where('master_id', $recordMaster->id)->where('reconciled',1)->get();
        foreach($recordDetails as $recordDetail){
            $this->confirmReconciliation($request->authUser, $recordDetail->value_date, $recordDetail->assessment_no, $recordDetail->credit);
        }
        return response()->json(["data"=>"Action successful"],200);
    }
    public function handlePurgeReconciliationRequest(Request $request){
        $validator = Validator::make($request->all(), [
            "uuid" => "required",
            "authUser" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $recordMaster = ReconciliationMaster::where("uuid", $request->uuid)->first();
        if(empty($recordMaster)){
            return response()->json([
                "errors"=>"Whoops! No record found!",
            ],422);
        }
        $recordDetails = Reconciliation::where('master_id', $recordMaster->id)->get();
        foreach($recordDetails as $recordDetail){
            $recordDetail->delete();
        }
        $recordMaster->delete();
        return response()->json(["data"=>"Action successful"],200);
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

    private function confirmReconciliation($authUser, $valueDate, $assessmentNo, $amount): bool
    {
        $payments = BillPaymentLog::where('assessment_no', $assessmentNo)->where('amount', $amount)->get();
        if(count($payments) > 0){
            foreach($payments as $payment){
                $payment->reconciled = 1;
                $payment->value_date = $valueDate;
                $payment->reconciled_by = $authUser;
                $payment->date_reconciled = now();
                $payment->save();
            }
            return true;
        }
        return false;
    }

}

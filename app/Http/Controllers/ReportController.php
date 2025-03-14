<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillDetailExtractResource;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\CustomerStatementResource;
use App\Http\Resources\OutstandingBillResource;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $lgaId = $request->keyword;
        $type = $request->type;
        $from = $request->from;
        $to = $request->to;
        switch ($type){
            case 'lga':
                $bills = BillPaymentLog::getPaymentReportByLGADateRange($lgaId, $request->from, $request->to);
                return response()->json([
                    //"bill"=>new BillDetailExtractResource($bills->first()),
                    "data"=>CustomerStatementResource::collection($bills),
                ],200);

                $bills = Billing::when($lgaId > 0, function($query) use ($lgaId) {
                    return $query->where('lga_id', $lgaId);
                })->whereBetween('entry_date',[$from, $to] )
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
            case 'zone':
                $bills =  Billing::where('zone_name',$lgaId) //zone_name : A1 || C2...
                ->where('status', 4)
                    ->where('objection', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
            case 'ward':
                $bills =  Billing::where('ward',$lgaId) //zone_name : Lokoja E ...
                ->where('status', 4)
                    ->where('objection', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
        }

    }
}

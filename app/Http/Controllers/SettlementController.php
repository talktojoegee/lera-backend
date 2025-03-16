<?php

namespace App\Http\Controllers;

use App\Http\Resources\SettlementReportResource;
use App\Http\Resources\SettlementSetupResource;
use App\Models\SettlementReportSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettlementController extends Controller
{
    public function getSettings(){
        return new SettlementSetupResource(SettlementReportSetup::first());
    }


    public function storeSettlementSetup(Request $request){
        $validator = Validator::make($request->all(), [
            "bank" => "required",
            "newwaves" => "required",
            "kgirs" => "required",
            "lga" => "required"
        ], [
            "bank.required" => "Enter value for bank",
            "newwaves.required" => "Enter value for Newwaves",
            "kgirs.required" => "Enter value for KGIRS",
            "lga.required" => "Enter value for LGA"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages(),
                "message"=>"Validation error",
                "detail"=>"All fields are required."
            ], 422);
        }
        $setup = SettlementReportSetup::first();
        if(empty($setup)){
            SettlementReportSetup::create([
                'bank'=>$request->bank ?? 0,
                'newwaves'=>$request->newwaves ?? 0,
                'kgirs'=>$request->kgirs ?? 0,
                'lga'=>$request->lga ?? 0,
            ]);
        }else{
            $setup->bank = $request->bank ?? 0;
            $setup->newwaves = $request->newwaves ?? 0;
            $setup->kgirs = $request->kgirs ?? 0;
            $setup->lga = $request->lga ?? 0;
            $setup->save();
        }
        return response()->json(['data'=>'Action successful'],201);
    }

    public function generateSettlementReport(Request $request){
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
        $report = DB::table('bill_payment_logs')
            ->join('lgas', 'lgas.id', '=', 'bill_payment_logs.lga_id')
            ->orderBy('lgas.id', 'ASC')
            ->whereBetween('entry_date', [$from, $to])
            ->get();
        return response()->json([
            "data"=>SettlementReportResource::collection($report)
        ]);
    }
}

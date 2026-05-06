<?php

namespace App\Http\Controllers;

use App\Http\Resources\PAVResource;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyAssessmentValueController extends Controller
{
    public function __construct(){

    }

    public function storePAV(Request $request){
        $validator = Validator::make($request->all(),[
            "pav_code"=>"required|unique:property_assessment_values,pav_code",
            "zones"=>"required",
            "property_type"=>"required",
            "annual_due"=>"required|numeric",
        ],[
            "pav_code.required"=>"PAV Code is required",
            "pav_code.unique"=>"PAV Code already exists",
            "zones.required"=>"Zone is required",
            "property_type.required"=>"Property type is required",
            "annual_due.required"=>"Annual due amount is required",
            "annual_due.numeric"=>"Annual due must be a numeric value",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        PropertyAssessmentValue::create([
            "pav_code"=>$request->pav_code,
            "zones"=>$request->zones,
            "property_type"=>$request->property_type,
            "annual_due"=>$request->annual_due,
        ]);
        return response()->json(['message' => 'Success! PAV added.'], 201);
    }



    public function updatePAV(Request $request){
        $validator = Validator::make($request->all(),[
            "id"=>"required|exists:property_assessment_values,id",
            "pav_code"=>"required|unique:property_assessment_values,pav_code,".$request->id,
            "zones"=>"required",
            "property_type"=>"required",
            "annual_due"=>"required|numeric",
        ],[
            "id.required"=>"ID is required",
            "id.exists"=>"PAV record not found",
            "pav_code.required"=>"PAV Code is required",
            "pav_code.unique"=>"PAV Code already exists",
            "zones.required"=>"Zone is required",
            "property_type.required"=>"Property type is required",
            "annual_due.required"=>"Annual due amount is required",
            "annual_due.numeric"=>"Annual due must be a numeric value",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        $propertyAssessment = PropertyAssessmentValue::findOrFail($request->id);
        $propertyAssessment->update([
            "pav_code" => $request->pav_code,
            "zones" => $request->zones,
            "property_type" => $request->property_type,
            "annual_due" => $request->annual_due,
        ]);

        return response()->json(['message' => 'Success! Changes saved'], 200);
    }



    public function showAllPAVs(Request $request){
        $limit = $request->input('limit', 15);
        $skip = $request->input('skip', 0);

        return response()->json([
            'data'=>PAVResource::collection($this->getPAVs($limit, $skip)),
            'total'=>PropertyAssessmentValue::count()
        ]);
    }

    private function getPAVs($limit, $skip){
        return PropertyAssessmentValue::skip($skip)->take($limit)->orderBy('id', 'DESC')->get();
    }


    public function showDistinctSectors(){
        $sectors = DB::table('property_assessment_values')
            ->select('property_type')
            ->distinct()
            ->get();
        return response()->json([
            'data'=>$sectors
        ]);
    }

    public function storeLUC(Request $request){
        $validator = Validator::make($request->all(),[
            "amount"=>"required",
        ],[
            "amount.required"=>"Amount is required",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = MinimumLuc::first();
        if(!empty($record)){
            $record->amount = $request->amount ?? 0;
            $record->save();
            return response()->json(['message' => 'Action successful!'], 200);
        }
        MinimumLuc::create([
            'amount'=>$request->amount
        ]);
        return response()->json(['data'=>"Action successful!"], 200);
    }


    public function getLUC(){
        $record = MinimumLuc::first();
        return response()->json(['data'=>$record], 200);
    }
}

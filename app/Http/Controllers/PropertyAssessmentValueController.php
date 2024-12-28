<?php

namespace App\Http\Controllers;

use App\Http\Resources\PAVResource;
use App\Http\Resources\PropertyClassificationResource;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyAssessmentValueController extends Controller
{
    public function __construct(){

    }

    public function storePAV(Request $request){
        $validator = Validator::make($request->all(),[
            "pav_code"=>"required",
            "assessed_amount"=>"required",
            "value_rate"=>"required",
            "class_id"=>"required",
            "zone"=>"required",
            "description"=>"required",
        ],[
            "pav_code.required"=>"PAV Code is required",
            "assessed_amount.required"=>"Enter Assessed Amount",
            "value_rate.required"=>"Enter value rate",
            "class_id.required"=>"Indicate property classification",
            "zone.required"=>"Zone is required",
            "description.required"=>"Enter a brief description",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        PropertyAssessmentValue::create([
            "assessed_amount"=>$request->assessed_amount,
            "value_rate"=>$request->value_rate,
            "occupancy"=>$request->description,
            "pav_code"=>$request->pav_code,
            "zones"=>implode(", ",$request->zone),
            "class_id"=>$request->class_id,
        ]);
        return response()->json(['message' => 'Success! PAV added.'], 201);
    }


    public function showAllPAVs(){
        return PAVResource::collection(PropertyAssessmentValue::all());
    }
}

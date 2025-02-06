<?php

namespace App\Http\Controllers;

use App\Http\Resources\DepreciationResource;
use App\Http\Resources\LGAResource;
use App\Models\Depreciation;
use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepreciationController extends Controller
{

    public function __construct(){

    }



    public function createDepreciation(Request $request){
        $validator = Validator::make($request->all(),[
            "range"=>"required",
            //"ageTo"=>"required",
            "depreciationRate"=>"required",
            "value"=>"required"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $value = (100 - $request->depreciationRate);
        if($value == $request->value){
            Depreciation::create([
                'depreciation_rate'=>$request->depreciationRate,
                'range'=>$request->range,
                //'building_age_to'=>$request->ageTo,
                'value'=>(100 - $request->depreciationRate),
            ]);
            return response()->json(['message' => 'Success! Depreciation added.'], 201);
        }else{
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }


    }


    public function showAllDepreciations(){
        return DepreciationResource::collection(Depreciation::all());
    }
}

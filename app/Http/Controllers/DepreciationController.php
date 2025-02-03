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
            "ageFrom"=>"required",
            "ageTo"=>"required",
            "depreciationRate"=>"required",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        Depreciation::create([
            'depreciation_rate'=>$request->depreciationRate,
            'building_age_from'=>$request->ageFrom,
            'building_age_to'=>$request->ageTo,
            ]);
        return response()->json(['message' => 'Success! Depreciation added.'], 201);
    }


    public function showAllDepreciations(){
        return DepreciationResource::collection(Depreciation::all());
    }
}

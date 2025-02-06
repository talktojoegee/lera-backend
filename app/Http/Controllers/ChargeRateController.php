<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChargeRateResource;
use App\Models\ChargeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChargeRateController extends Controller
{
    public function __construct(){

    }

    public function createChargeRate(Request $request){
        $validator = Validator::make($request->all(),[
            "rate"=>"required",
            "occupancy"=>"required",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        ChargeRate::create([
            'rate'=>$request->rate,
            'occupancy'=>$request->occupancy,
        ]);
        return response()->json(['message' => 'Success! Charge rate.'], 201);



    }


    public function showAllChargeRates(){
        return ChargeRateResource::collection(ChargeRate::all());
    }
}

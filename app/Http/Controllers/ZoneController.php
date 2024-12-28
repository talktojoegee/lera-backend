<?php

namespace App\Http\Controllers;

use App\Http\Resources\LGAResource;
use App\Http\Resources\ZoneResource;
use App\Models\Lga;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZoneController extends Controller
{


    public function __construct(){

    }

    public function createZone(Request $request){
        $validator = Validator::make($request->all(),[
            "zoneName"=>"required",
            "subZone"=>"required|unique:zones,sub_zone",
        ],[
            "zoneName.required"=>"Enter Zone name",
            "subZone.required"=>"Enter sub-zone"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        Zone::create(['zone_name'=>$request->zoneName, 'sub_zone'=>$request->subZone]);
        return response()->json(['message' => 'Success! Zone added.'], 201);
    }


    public function showAllZones(){
        return ZoneResource::collection(Zone::all());
    }
}

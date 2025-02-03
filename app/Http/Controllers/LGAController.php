<?php

namespace App\Http\Controllers;

use App\Http\Resources\LGAResource;
use App\Models\Lga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LGAController extends Controller
{



    public function __construct(){

    }

    public function createLGA(Request $request){
        $validator = Validator::make($request->all(),[
            "lgaName"=>"required|unique:lgas,lga_name"
        ],[
            "lgaName.required"=>"What is the LGA name?"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        Lga::create(['lga_name'=>$request->lgaName]);
        return response()->json(['message' => 'Success! LGA added.'], 201);
    }


    public function showAllLGAs(){
        return LGAResource::collection(Lga::fetchAllLGAs());
    }

}

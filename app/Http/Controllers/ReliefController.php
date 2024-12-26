<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReliefResource;
use App\Models\Relief;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReliefController extends Controller
{
    public function __construct(){

    }


    public function storeReliefSettings(Request $request){
        $validator = Validator::make($request->all(),[
            "description"=>"required",
            "item"=>"required",
            "rate"=>"required",
        ],[
            "description.required"=>"Describe this relief request",
            "item.required"=>"Enter item name",
            "rate.required"=>"Indicate rate",
        ]);
        if($validator->fails()){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);;
        }
        Relief::create($request->all());
        return response()->json(['message'=>"Success! Record added."],201);

    }


    public function showReliefSetup(){
        return ReliefResource::collection(Relief::all());
    }
}

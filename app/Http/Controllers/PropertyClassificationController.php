<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyClassificationResource;
use App\Models\PropertyClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyClassificationController extends Controller
{

    public function __construct(){

    }

    public function storeClass(Request $request){
        $validator = Validator::make($request->all(),[
            "className"=>"required|unique:property_classifications,class_name"
        ],[
            "className.required"=>"Enter class name",
            "className.unique"=>"This property classification already exists on the system",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        PropertyClassification::create(['class_name'=>$request->className]);
        return response()->json(['message' => 'Success! Property classification added.'], 201);
    }


    public function showAllPropertyClassifications(){
        return PropertyClassificationResource::collection(PropertyClassification::all());
    }
}

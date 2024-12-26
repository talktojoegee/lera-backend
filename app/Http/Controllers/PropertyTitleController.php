<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyTitleResource;
use App\Models\PropertyTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyTitleController extends Controller
{

    public function __construct(){

    }

    public function storePropertyTitle(Request $request){
        $validator = Validator::make($request->all(),[
            "title"=>"required|unique:property_titles,title"
        ],[
            "title.required"=>"What is the property title?",
            "title.unique"=>"This property title already exists"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        PropertyTitle::create($request->all());
        return response()->json(['message' => 'Success! Property title added.'], 201);
    }


    public function showAllPropertyTitles(){
        return PropertyTitleResource::collection(PropertyTitle::all());
    }
}

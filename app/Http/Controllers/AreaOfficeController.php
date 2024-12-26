<?php

namespace App\Http\Controllers;

use App\Http\Resources\AreaOfficeResource;
use App\Models\AreaOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AreaOfficeController extends Controller
{
    public function __construct(){

    }

    public function storeAreaOffice(Request $request){
        $validator = Validator::make($request->all(),[
            "areaOfficeId"=>"required|unique:area_offices,area_office_id",
            "areaName"=>"required|unique:area_offices,area_name",
            "lgaId"=>"required",
        ],[
            "areaOfficeId.required"=>"Enter office ID",
            "title.unique"=>"This office ID already exists on the system",

            "areaName.required"=>"Enter area name",
            "areaName.unique"=>"This area name already exists on the system",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        AreaOffice::create([
            "area_name"=>$request->areaName,
            "area_office_id"=>$request->areaOfficeId,
            "lga_id"=>$request->lgaId,
        ]);
        return response()->json(['message' => 'Success! Area office added'], 201);
    }


    public function showAllAreaOffices(){
        return AreaOfficeResource::collection(AreaOffice::all());
    }
}

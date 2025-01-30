<?php

namespace App\Http\Controllers;

use App\Http\Resources\OwnersResource;
use App\Http\Resources\ReliefResource;
use App\Models\Owner;
use App\Models\PropertyList;
use App\Models\Relief;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OwnersController extends Controller
{
    public function __construct(){

    }


    public function storeOwner(Request $request){
        $validator = Validator::make($request->all(),[
            "lga_id"=>"required",
            "kgtin"=>"required",
            "name"=>"required",
            "telephone"=>"required",
            "email"=>"required",
            "resAddress"=>"required",
        ],[
            "lga_id.required"=>"Select LGA",
            "kgtin.required"=>"Enter KGTIN",
            "name.required"=>"Enter owner name",
            "telephone.required"=>"Telephone number is required",
            "email.required"=>"Enter email address",
            "resAddress.required"=>"Enter residential address",
        ]);
        if($validator->fails()){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);;
        }
        Owner::create([
            "email"=>$request->email,
            "kgtin"=>$request->kgtin,
            "name"=>$request->name,
            "res_address"=>$request->resAddress,
            "telephone"=>$request->telephone,
            "lga_id"=>$request->lga_id,
            "added_by"=>1,//Auth::user()->id,
        ]);
        return response()->json(['message'=>"Success! Record added."],201);

    }




    public function showAllOwners(){
        return OwnersResource::collection(Owner::all());
    }

    public function showOwnerByKGTin(Request $request){
        $owner = Owner::where('kgtin', $request->kgtin)->first();
       /* if (!$owner) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }*/
        return $owner ? new OwnersResource($owner) : response()->json([]);
        //return new OwnersResource($owner);
    }


    public function saveOwnerChanges(Request $request){
        $validator = Validator::make($request->all(),[
            //"lga_id"=>"required",
            "kgtin"=>"required",
            "name"=>"required",
            "mobileNo"=>"required",
            "propertyId"=>"required"
        ],[
            //"lga_id.required"=>"Select LGA",
            "kgtin.required"=>"Enter KGTIN",
            "name.required"=>"Enter owner name",
            "mobileNo.required"=>"Telephone number is required",
        ]);
        if($validator->fails()){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);;
        }
        $owner = Owner::find($request->id);
        if(!empty($owner)){
            $owner->email = $request->email ?? '';
            $owner->kgtin = $request->kgtin ?? '';
            $owner->name = $request->name ?? '';
            $owner->telephone = $request->mobileNo ?? '';
            $owner->save();
        }else{
            Owner::create([
                "email"=>$request->email,
                "kgtin"=>$request->kgtin,
                "name"=>$request->name,
                "res_address"=>$request->resAddress,
                "telephone"=>$request->mobileNo,
                "lga_id"=> 1,// $request->lga_id,
                "added_by"=>$request->addedBy
            ]);
        }
        //update property details
        $property = PropertyList::find($request->propertyId);
        if(!empty($property)){
            $property->owner_email = $request->email ?? '' ;
            $property->owner_gsm = $request->mobileNo ?? '' ;
            $property->owner_kgtin = $request->kgtin ?? '' ;
            $property->owner_name = $request->name ?? '' ;
            $property->save();
        }
        return response()->json(['message'=>"Success! Record added."],201);

    }
}

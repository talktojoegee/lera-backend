<?php

namespace App\Http\Controllers;

use App\Http\Resources\PAVResource;
use App\Http\Resources\PropertyClassificationResource;
use App\Http\Resources\SectorResource;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyAssessmentValueController extends Controller
{
    public function __construct(){

    }

    public function storePAV(Request $request){
        $validator = Validator::make($request->all(),[
            "pav_code"=>"required",
            //"assessed_amount"=>"required",
            //"value_rate"=>"required",
            "class_id"=>"required",
            "zone"=>"required",
            "ba"=>"required",
            "br"=>"required",
            "rr"=>"required",
            "lr"=>"required",
            "description"=>"required",
            "syncWord"=>"required",
        ],[
            "pav_code.required"=>"PAV Code is required",
            //"assessed_amount.required"=>"Enter Assessed Amount",
            //"value_rate.required"=>"Enter value rate",
            "class_id.required"=>"Indicate property classification",
            "zone.required"=>"Zone is required",
            "description.required"=>"Enter a brief description",
            "ba.required"=>"Enter BA value",
            "br.required"=>"BR value is required",
            "rr.required"=>"RR value is required",
            "lr.required"=>"LR value is required",
            "syncWord.required"=>"Sync word is required",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        PropertyAssessmentValue::create([
            //"assessed_amount"=>$request->assessed_amount,
            //"value_rate"=>$request->value_rate,
            "property_use"=>$request->description,
            "pav_code"=>$request->pav_code,
            "zones"=>implode(", ",$request->zone),
            "class_id"=>$request->class_id,
            "lr"=>$request->lr,
            "ba"=>$request->ba,
            "rr"=>$request->rr,
            "br"=>$request->br,
            "sync_word"=>$request->syncWord ?? '',
        ]);
        return response()->json(['message' => 'Success! PAV added.'], 201);
    }



    public function updatePAV(Request $request){
        $validator = Validator::make($request->all(),[
            "pav_code"=>"required",
            //"assessed_amount"=>"required",
            //"value_rate"=>"required",
            "class_id"=>"required",
            "zone"=>"required",
            "ba"=>"required",
            "br"=>"required",
            "rr"=>"required",
            "lr"=>"required",
            "description"=>"required",
            "id"=>"required",
            "syncWord"=>"required",
        ],[
            "pav_code.required"=>"PAV Code is required",
            //"assessed_amount.required"=>"Enter Assessed Amount",
            //"value_rate.required"=>"Enter value rate",
            "class_id.required"=>"Indicate property classification",
            "zone.required"=>"Zone is required",
            "description.required"=>"Enter a brief description",
            "ba.required"=>"Enter BA value",
            "br.required"=>"BR value is required",
            "rr.required"=>"RR value is required",
            "lr.required"=>"LR value is required",
            "id.required"=>"",
            "syncWord.required"=>"",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        $propertyAssessment = PropertyAssessmentValue::findOrFail($request->id); // Ensure $id is passed correctly
        $propertyAssessment->update([
            //"assessed_amount" => $request->assessed_amount,
            //"value_rate" => $request->value_rate,
            "property_use" => $request->description,
            "pav_code" => $request->pav_code,
            "zones" => isset($request->zone) ? implode(", ", $request->zone) : null,
            "class_id" => $request->class_id,
            "lr" => $request->lr,
            "ba" => $request->ba,
            "rr" => $request->rr,
            "br" => $request->br,
            "sync_word" => $request->syncWord ?? '',
        ]);

        return response()->json(['message' => 'Success! Changes saved'], 200);
    }



    public function showAllPAVs(Request $request){
        return response()->json([
            'data'=>PAVResource::collection($this->getBillSetup($request->limit, $request->skip)),
            'total'=>PropertyAssessmentValue::count()
        ]);
    }

    private function getBillSetup($limit, $skip){
        return PropertyAssessmentValue::skip($skip)->take($limit)->orderBy('id', 'DESC')->get();
    }


    public function showDistinctSectors(){
        $sectors = DB::table('property_assessment_values')
            ->select('property_use')
            ->distinct()
            ->get();
        return response()->json([
            'data'=>SectorResource::collection( $sectors)
        ]);
        //skip($skip)->take($limit)->orderBy('id', 'DESC')->get();
    }

    public function storeLUC(Request $request){
        $validator = Validator::make($request->all(),[
            "amount"=>"required",
        ],[
            "amount.required"=>"Amount is required",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = MinimumLuc::first();
        if(!empty($record)){
            $record->amount = $request->amount ?? 0;
            $record->save();
            return response()->json(['message' => 'Action successful!'], 200);
        }
        MinimumLuc::create([
            'amount'=>$request->amount
        ]);
        return response()->json(['data'=>"Action successful!"], 200);
    }


    public function getLUC(){
        $record = MinimumLuc::first();
        return response()->json(['data'=>$record], 200);
    }
}

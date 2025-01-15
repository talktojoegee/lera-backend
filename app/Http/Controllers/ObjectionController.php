<?php

namespace App\Http\Controllers;

use App\Http\Resources\ObjectionResource;
use App\Models\Lga;
use App\Models\Objection;
use App\Models\ObjectionAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ObjectionController extends Controller
{
    public function __construct(){

    }

    public function handleNewObjection(Request $request){

        $validator = Validator::make($request->all(),
            [
            "reason"=>"required",
            "selectedReliefs"=>"required|array",
            "selectedReliefs.*"=>"required",
            "submittedBy"=>"required",
            "billId"=>"required",
        ],
            [
            "reason.required"=>"Type your objection in the field provided.",
            "selectedReliefs.required"=>"Choose at least one relief",
            "selectedReliefs.array"=>"Choose at least one relief",
            "submittedBy.required"=>"Who is submitting this request?",
            "billId.required"=>"Whoops! Something is missing.",
        ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $uniqueNumber = time();
        $objection = Objection::create([
            'bill_id'=>$request->billId,
            'submitted_by'=>$request->submittedBy,
            'reason'=>$request->reason,
            'relief_ids'=>implode(',', $request->selectedReliefs),
        ]);

        if ($request->hasFile('uploadedFiles')) {
            foreach ($request->file('uploadedFiles') as $file) {
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('uploads', $fileName, 'public');
                $fileSize = $file->getSize();
                    ObjectionAttachment::create([
                    'objection_id' => $objection->id,
                    'attachment' => $fileName,
                    'filename' => $path,
                    'size' => $fileSize,
                ]);
            }
        }

        return response()->json(['message' => 'Success! Action successful.'], 201);
    }

    public function showObjectionListByStatus(Request $request){
        $status = $request->status;
        $skip = $request->skip;
        $limit = $request->limit;
        $records = Objection::fetchObjectionsByStatus($status, $limit, $skip);
        return response()->json([
            'data'=>ObjectionResource::collection($records),
            'total'=>Objection::fetchObjectionByParam($status)
            ]);
    }


    public function showObjectionDetail(Request $request){
        $record = Objection::where('request_id', $request->requestId)->first();
        if (!$record) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }
        return new ObjectionResource($record);
    }


    public function actionObjection(Request $request){

        $validator = Validator::make($request->all(),
            [
                "requestId"=>"required",
                "actionedBy"=>"required",
                "action"=>"required",
            ],
            [
                "requestId.required"=>"Whoops! Something is missing",
                "actionedBy.required"=>"Who action this objection?",
                "action.required"=>"Missing status update",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = Objection::where('request_id', $request->requestId)->first();
        if (!$record) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }
        $record->status = $request->action;
        $record->actioned_by = $request->actionedBy;
        $record->date_actioned = now();
        $record->save();


        return response()->json(['message' => 'Success! Action successful.'], 201);
    }
}

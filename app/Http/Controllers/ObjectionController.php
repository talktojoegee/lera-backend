<?php

namespace App\Http\Controllers;

use App\Http\Resources\ObjectionResource;
use App\Models\Billing;
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
            "selectedReliefs"=>"required",
            //"selectedReliefs"=>"required|array",
            //"selectedReliefs.*"=>"required",
            "submittedBy"=>"required",
            "billId"=>"required",
        ],
            [
            "reason.required"=>"Type your objection in the field provided.",
            "selectedReliefs.required"=>"Choose at least one relief",
            //"selectedReliefs.array"=>"Choose at least one relief",
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
            'request_id'=>$uniqueNumber,
            'bill_id'=>$request->billId,
            'submitted_by'=>$request->submittedBy,
            'reason'=>$request->reason,
            'relief_ids'=>$request->selectedReliefs,
           // 'relief_ids'=>implode(',', $request->selectedReliefs),
        ]);

        $file_names = $_FILES["uploadedFiles"]["name"];

    /*    for ($i = 0; $i < count($file_names); $i++) {
            $file_name=$file_names[$i];
            $extension = end(explode(".", $file_name));

            $original_file_name = pathinfo($file_name, PATHINFO_FILENAME);

            $file_url = $original_file_name . "-" . date("YmdHis") . "." . $extension;

            move_uploaded_file($_FILES["file"]["tmp_name"][$i], $folderPath . $file_url);

        }*/

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
        if($request->action == 1 || $request->action == 2){
            $record->status = $request->action;
            $record->actioned_by = $request->actionedBy;
            $record->date_actioned = now();
            $record->save();
        }
        if($request->action == 3){ //authorization
            $record->status = $request->action;
            $record->luc_amount = $request->lucAmount ?? 0;
            $record->rate = $request->chargeRate ?? 0;
            $record->assess_value = $request->assessedValue ?? 0;
            $record->authorized_by = $request->actionedBy;
            $record->date_authorized = now();
            $record->save();
        }
        if($request->action == 4){ //approved
            $record->status = $request->action;
            //$record->luc_amount = $request->lucAmount ?? 0;
            //$record->rate = $request->chargeRate ?? 0;
            //$record->assess_value = $request->assessedValue ?? 0;
            $record->approved_by = $request->actionedBy;
            $record->date_approved = now();
            $record->save();

            //let's archive bill & generate a new one;
            $bill = Billing::find($record->bill_id);
            if (empty($bill)) {
                return response()->json([
                    'message' => 'Whoops! Something went wrong.'
                ], 404);
            }else{
                $bill->status = 4; //archived
                $bill->objection = 1; //archived through objection
                $bill->save();
                //new bill
                $uniqueNumber = uniqid();
                $billAmount = $record->luc_amount ?? 0;
                $billing = new Billing();
                $billing->building_code = $bill->building_code ?? null;
                $billing->assessment_no = $uniqueNumber;

                $billing->assessed_value = $record->assess_value ?? 0;
                $billing->bill_amount = $billAmount ?? 0;
                $billing->bill_rate = $record->rate ?? 0;

                $billing->year = $bill->year;
                $billing->entry_date = $bill->entry_date;
                $billing->billed_by = 1;
                $billing->paid = 0;
                $billing->paid_amount = 0.00;
                $billing->objection = 0;
                $billing->lga_id = $bill->lga_id; //$request->lgaId;
                $billing->property_id = $bill->property_id;

                $billing->pav_code = $bill->pav_code;
                $billing->zone_name = $bill->sub_zone ?? '';
                $billing->url = substr(sha1(time()), 29, 40);
                $billing->save();
            }

        }
        return response()->json(['message' => 'Success! Action successful.'], 201);
    }
}

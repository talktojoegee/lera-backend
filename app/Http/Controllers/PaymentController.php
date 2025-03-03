<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use App\Models\Owner;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yabacon\Paystack;

class PaymentController extends Controller
{


    public function handlePaymentRequest(Request $request){
        $validator = Validator::make($request->all(),[
            'name'=>'required',
            'mobileNo'=>'required',
            'email'=>'required|email',
            'amount'=>'required',
            'billId'=>'required',
            'paidBy'=>'required',
            'kgtin'=>'required',
        ],[
            'amount.required'=>"Enter an amount" ,
            'email.required'=>"Enter a valid email address" ,
            'billId.required'=>"" ,
            'paidBy.required'=>"" ,
            'email.email'=>"Enter a valid email address" ,
            'mobileNo.required'=>"Enter mobile number" ,
            'kgtin.required'=>"KGTIN is required" ,
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::find($request->billId);
        if(empty($bill)){
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }

        $bill->paid_amount = $request->amount;
        $bill->paid_by = $request->paidBy;
        $bill->date_paid = now();
        $bill->payment_ref = substr(sha1(time()),30,40);
        $bill->save();
        if($bill->bill_amount >= $bill->paid_amount){
            $bill->paid = 1;
            $bill->save();
        }
        //log it
        BillPaymentLog::create([
            'bill_master'=>$request->billId,
            'paid_by'=>$request->paidBy,
            'amount'=>$request->amount,
            'trans_ref'=>$request->transRef,
            'reference'=>$request->reference,
        ]);
        //update property
        $property = PropertyList::where('building_code', $bill->building_code)->first();
        if(!empty($property)){
            $property->owner_email = $request->email;
            $property->owner_name = $request->name;
            $property->owner_gsm = $request->mobileNo;
            $property->owner_kgtin = $request->kgtin ?? null;
            $property->save();
        }
        //update owner
        if(!empty($property) && !empty($bill)){
            $owner = Owner::where('kgtin', $request->kgtin)->first();
            if(empty($owner)){
                Owner::create([
                    "email"=>$request->email,
                    "kgtin"=>$request->kgtin,
                    "name"=>$request->name,
                    "telephone"=>$request->mobileNo,
                    "lga_id"=>$bill->lga_id,
                    "added_by"=>$request->paidBy,
                    "res_address"=>$property->address
                ]);
            }else{
                $owner->email = $request->email;
                $owner->kgtin = $request->kgtin;
                $owner->name = $request->name;
                $owner->telephone = $request->mobileNo;
                $owner->lga_id = $bill->lga_id;
                $owner->res_address = $property->address;
                $owner->save();
            }
        }
        //Kogi rems notification register
        KogiRemsNotification::create([
            "assessmentno"=>$bill->assessment_no,
            "buildingcode"=>$bill->building_code,
            "kgtin"=>$request->kgtin ?? null,
            "name"=>$request->name,
            "amount"=>$request->amount,
            "phone"=>$request->mobileNo,
            "email"=>$request->email,
            "transdate"=>$request->transdate ?? now(),
            "transRef"=>$request->reference,
            "paymode"=>$request->paymode ?? "POS",
        ]);

        return response()->json(['data'=>"Payment recorded"], 201);

    }


    public function processOnlinePayment(Request $request){

        $reference = isset($request->reference) ? $request->reference : '';
        if(!$reference){
            die('No reference supplied');
        }
        $paystack = new Paystack(config('app.paystack_secret_key'));
        try {
            //verify using the library
            $tranx = $paystack->transaction->verify([
                'reference'=>$reference, //unique to transactions
            ]);
        }catch (Paystack\Exception\ApiException $exception){
            session()->flash("error", "Whoops! Something went wrong.");
            return redirect()->route('top-up');
        }
        if ('success' === $tranx->data->status) {
            try {
                //return dd($tranx->data->metadata->cost);
                $transaction_type = $tranx->data->metadata->transaction ;
                $account = $tranx->data->metadata->account ;
                $category = $tranx->data->metadata->category ;
                switch ($transaction_type){
                    case 4:
                        $branchId = Auth::user()->branch;
                        $defaultCurrency = env('NAIRA_ID');
                        $note = "Purchase of bulk SMS units. The amount includes convenience fee";
                        $this->bulksmsaccount->creditAccount($reference,
                            $tranx->data->amount, //50900
                            $tranx->data->metadata->cost, $tranx->data->metadata->user); //cost = 500
                        $this->cashbook->addCashBook($branchId, $category, $account,
                            $defaultCurrency, 2, 0, 2, now(),
                            $note, $note,
                            ($tranx->data->amount + $tranx->data->metadata->cost)/100,  0,
                            substr(sha1(time()),31,40),date('m', strtotime(now())),date('Y', strtotime(now())));
                        break;
                }
                switch ($transaction_type){
                    case 4:
                        session()->flash("success", "Your top-up transaction was successful.");
                        return redirect()->route('top-up');
                }
            }catch (Paystack\Exception\ApiException $ex){

            }

        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillPaymentLog;
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
        ],[
            'amount.required'=>"Enter an amount" ,
            'email.required'=>"Enter a valid email address" ,
            'billId.required'=>"" ,
            'paidBy.required'=>"" ,
            'email.email'=>"Enter a valid email address" ,
            'mobileNo.required'=>"Enter mobile number" ,
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

        $bill->paid_amount += $request->amount;
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

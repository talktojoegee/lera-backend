<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yabacon\Paystack;

class PaymentController extends Controller
{


    public function handlePaymentRequest(Request $request){
        $this->validate($request,[
            'amount'=>'required',
            'email'=>'required|email',
            'ownerId'=>'required',
        ],[
            'amount.required'=>"Enter an amount" ,
            'email.required'=>"Enter a valid email address" ,
            'ownerId.required'=>"" ,
            'email.email'=>"Enter a valid email address" ,
        ]);
        try{
            $paystack = new Paystack(env('PAYSTACK_SECRET_KEY'));
            $cost = $request->amount;
            $builder = new Paystack\MetadataBuilder();
            $builder->withCost($cost);
            $builder->withUser($request->ownerId);

            $metadata = $builder->build();
            $charge = $cost < 2500 ? ceil($cost*1.7/100) : ceil($cost*1.7/100)+100;
            $tranx = $paystack->transaction->initialize([
                'amount'=>($cost+$charge)*100,
                'email'=>$request->email,
                'reference'=>substr(sha1(time()),23,40),
                'metadata'=>$metadata
            ]);
            $response = $tranx->data->authorization_url;
            if ('success' === $response->data->status) {
                $ownerId = $tranx->data->metadata->user ;
                $cost = $tranx->data->metadata->cost ;

            }
            //return redirect()->to($tranx->data->authorization_url)->send();
        }catch (Paystack\Exception\ApiException $exception){
            session()->flash("error", "Whoops! Something went wrong. Try again.");
            return back();
        }
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

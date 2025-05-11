<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyKogiRemsJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use App\Models\Owner;
use App\Models\PropertyList;
use App\Traits\EmailTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yabacon\Paystack;

class PaymentController extends Controller
{
    use EmailTrait;

    public $appToken = "b4234351ec7218cadf89300d2402e82b";
    public $selfServiceToken = "55f5ae74fdaeb019f432216cc89f4029";
    public $eTranzactToken = "ytw6h351ec7897wef89300d240u87n";



    public function handlePaymentRequest(Request $request){
        $validator = Validator::make($request->all(),[
            'name'=>'required',
            'mobileNo'=>'required',
            'email'=>'required|email',
            'amount'=>'required',
            'billId'=>'required',
            'paidBy'=>'required',
            'kgtin'=>'required',
            'appToken'=>'required',
            //'reference' => 'required|string|unique:bill_payment_logs,reference',
        ],[
            'amount.required'=>"Enter an amount" ,
            'email.required'=>"Enter a valid email address" ,
            'billId.required'=>"" ,
            'paidBy.required'=>"" ,
            'email.email'=>"Enter a valid email address" ,
            'mobileNo.required'=>"Enter mobile number" ,
            'kgtin.required'=>"KGTIN is required" ,
            'appToken.required'=>"Token required" ,
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::where("paid", 0)->where("id", $request->billId)->first();
        if($request->appToken != $this->appToken){
            return response()->json([
                'message' => "We can't process this request at the moment."
            ], 404);
        }
        if (empty($bill)) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }else{
            $billList = Billing::where('building_code', $bill->building_code)
                ->where('year','<=', $bill->year)
                ->where('paid', 0)
                ->orderBy('id', 'ASC')
                ->get();
            if(!empty($billList)){
                $transAmount = $request->amount;
                foreach ($billList as $item){
                    $paymentCode = substr(sha1(time()),31,40);
                    $receiptNo = substr(sha1(time()),20,32);
                    $balance = $item->bill_amount - $item->paid_amount;
                    $transBalance = $transAmount - $balance;
                    if($balance > 0){
                        if($transAmount > $balance){
                            $item->paid_amount += $balance;
                            $item->payment_ref = $request->transRef;
                            $item->paid = 1;
                            $item->date_paid = now();
                            $item->paid_by = $request->paidBy;
                            $item->save();
                        }
                        else{
                            if($transAmount > 0){
                                $item->paid_amount += $transAmount;
                                $item->payment_ref = $request->transRef;
                                $item->save();
                                if($item->paid_amount == $item->bill_amount){
                                    $item->paid = 1;
                                    $item->date_paid = now();
                                    $item->paid_by = $request->paidBy;
                                    $item->save();
                                }
                            }
                        }
                    }
                    $transAmount = $transBalance;
                }
            }
        }

        //log it
        $rec = Billing::find($request->billId);
        if(!empty($rec)){
            BillPaymentLog::create([
                'bill_master'=>$request->billId,
                'paid_by'=>$request->paidBy,
                'amount'=>$request->amount,
                'trans_ref'=>$request->transRef,
                'reference'=>$request->reference,
                'receipt_no'=>$receiptNo,
                'payment_code'=>$paymentCode ?? '',
                'assessment_no'=>$item->assessment_no,

                'building_code'=>$rec->building_code,
                'lga_id'=>$rec->lga_id ?? '',
                'ward'=>$rec->ward ?? '',
                'zone'=>$rec->zone_name ?? '',

                'bank_name'=>"Credo",
                'branch_name'=>"Credo",
                'pay_mode'=>"Webpay Credo",
                'customer_name'=>$request->name,
                'email'=>$request->email,
                'kgtin'=>$request->kgtin,
                "entry_date"=>Carbon::parse(now())->format('Y-m-d'),
                "token"=>$request->appToken ?? ''
            ]);
            $data = [
                "name"=>$request->name ?? '',
                "receiptNo"=>$receiptNo,
                "amount"=>$request->amount
            ];
          //  if(isset($request->email)) $this->sendEmail($request->email, 'Payment Acknowledgement', 'emails.receipt', $data);
        }

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
                    "res_address"=>$property->address ?? 'N/A'
                ]);
            }else{
                $owner->email = $request->email;
                $owner->kgtin = $request->kgtin;
                $owner->name = $request->name;
                $owner->telephone = $request->mobileNo;
                $owner->lga_id = $bill->lga_id;
                $owner->res_address = $property->address ?? 'N/A';
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
            "paymode"=>$request->paymode ?? "Credo",
            "bank_name"=> "eTranzact",
            "luc_amount"=>$bill->bill_amount,
        ]);

        NotifyKogiRemsJob::dispatch();

        return response()->json(['data'=>"Payment recorded"], 201);

    }

    public function handlePaymentRequestByAssessmentNo(Request $request){
        $validator = Validator::make($request->all(),[
            'name'=>'required',
            'mobileNo'=>'required',
            'email'=>'required|email',
            'amount'=>'required',
            'billId'=>'required',
            'paidBy'=>'required',
            'transRef'=>'required',
            'kgtin'=>'required',
            "appToken"=>"required",
           // 'reference' => 'required|string|unique:bill_payment_logs,reference',
        ],[
            'amount.required'=>"Enter an amount" ,
            'email.required'=>"Enter a valid email address" ,
            'billId.required'=>"" ,
            'paidBy.required'=>"" ,
            'transRef.required'=>"Transaction reference is missing" ,
            'email.email'=>"Enter a valid email address" ,
            'mobileNo.required'=>"Enter mobile number" ,
            'kgtin.required'=>"KGTIN is required" ,
            'appToken.required'=>"Token required" ,
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::where("paid", 0)->where("assessment_no", $request->billId)->first();
        if($request->appToken != $this->selfServiceToken){
            return response()->json([
                'message' => "We can't process this request at the moment."
            ], 404);
        }

        $reference = BillPaymentLog::where("reference", $request->transRef)->first();
        if($reference){
            return response()->json([
                'message' => "Duplicate transaction"
            ], 404);
        }

        $existingReference = BillPaymentLog::where("reference", $request->transRef)->first();

        if (empty($bill)) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }else{

            if(!$existingReference){
                $billList = Billing::where('building_code', $bill->building_code)
                    ->where('year','<=', $bill->year)
                    ->where('paid', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                if(!empty($billList)){
                    $transAmount = $request->amount;
                    foreach ($billList as $item){
                        $paymentCode = substr(sha1(time()),31,40);
                        $receiptNo = substr(sha1(time()),20,32);
                        $balance = $item->bill_amount - $item->paid_amount;
                        $transBalance = $transAmount - $balance;
                        if($balance > 0){
                            if($transAmount > $balance){
                                $item->paid_amount += $balance;
                                $item->payment_ref = $request->transRef;
                                $item->paid = 1;
                                $item->date_paid = now();
                                $item->paid_by = $request->paidBy;
                                $item->save();
                            }
                            else{
                                if($transAmount > 0){
                                    $item->paid_amount += $transAmount;
                                    $item->payment_ref = $request->transRef;
                                    $item->save();
                                    if($item->paid_amount == $item->bill_amount){
                                        $item->paid = 1;
                                        $item->date_paid = now();
                                        $item->paid_by = $request->paidBy;
                                        $item->save();
                                    }
                                }
                            }
                        }
                        $transAmount = $transBalance;
                    }
                }
            }





        }

        //log it
        $ref = substr(sha1(time()),29,40);
        $rec = Billing::where('assessment_no',$request->billId)->first();
        if(!empty($rec)){
            if(!$existingReference){
                BillPaymentLog::create([
                    'bill_master'=>$rec->id, //$request->billId,
                    'paid_by'=>$request->paidBy,
                    'amount'=>$request->amount,
                    'trans_ref'=>$request->transRef ?? $ref,
                    'reference'=>$request->transRef ?? $ref,
                    'receipt_no'=>$receiptNo,
                    'payment_code'=>$paymentCode ?? '',
                    'assessment_no'=>$item->assessment_no,

                    'building_code'=>$rec->building_code,
                    'lga_id'=>$rec->lga_id ?? '',
                    'ward'=>$rec->ward ?? '',
                    'zone'=>$rec->zone_name ?? '',

                    'bank_name'=>"Credo",
                    'branch_name'=>"Credo",
                    'pay_mode'=>"Webpay Credo",
                    'customer_name'=>$request->name,
                    'email'=>$request->email,
                    'kgtin'=>$request->kgtin,
                    "entry_date"=>Carbon::parse(now())->format('Y-m-d'),
                    "token"=>$request->appToken ?? ''
                ]);
                $data = [
                    "name"=>$request->name ?? '',
                    "receiptNo"=>$receiptNo,
                    "amount"=>$request->amount
                ];
                // if(isset($request->email)) $this->sendEmail($request->email, 'Payment Acknowledgement', 'emails.receipt', $data);
            }

        }

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
                    "res_address"=>$request->address ?? 'N/A'
                ]);
            }else{
                $owner->email = $request->email;
                $owner->kgtin = $request->kgtin;
                $owner->name = $request->name;
                $owner->telephone = $request->mobileNo;
                $owner->lga_id = $bill->lga_id;
                $owner->res_address = $request->address ?? 'N/A';
                $owner->save();
            }
        }
        //Kogi rems notification register
        if(!$existingReference){
            KogiRemsNotification::create([
                "assessmentno"=>$bill->assessment_no,
                "buildingcode"=>$bill->building_code,
                "kgtin"=>$request->kgtin ?? null,
                "name"=>$request->name,
                "amount"=>$request->amount,
                "phone"=>$request->mobileNo,
                "email"=>$request->email,
                "transdate"=>$request->transdate ?? now(),
                "transRef"=>$request->reference ?? $ref,
                "paymode"=>$request->paymode ?? "Credo",
                "bank_name"=> "eTranzact",
                "luc_amount"=>$bill->bill_amount,
            ]);

            NotifyKogiRemsJob::dispatch();
        }


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

        } //67C8755BD2579
    }
}

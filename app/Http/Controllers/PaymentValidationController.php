<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyKogiRemsJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use App\Models\Owner;
use App\Models\PropertyList;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentValidationController extends Controller
{
    public $eTranzactToken = "ytw6h351ec7897wef89300d240u87n";
    public function validatePayment(Request $request)
    {
        $payeeId = $request->query('PAYEE_ID');
        //$paymentType = $request->query('PAYMENT_TYPE');
        if (empty($payeeId)) {
            return  response("PAYEE_ID=$payeeId&~PAYMENT_TYPE=LUC&~FeeStatus=PAYEE ID is Empty");
        }
        $bill = Billing::where('assessment_no', $payeeId)
            ->where('objection', 0)
            ->where('status', 4) //approved
            //->where('paid', 0)
            ->first();
        if(empty($bill)){
            return response()->json([
                "FeeRequest" => [
                    "PayeeID" => $payeeId,
                    "FeeStatus" => "Invalid Payee ID "
                ]
            ]);
        }

        /* if ($bill->status != 4) { //approved
             return response()->json([
                 "FeeRequest" => [
                     "PayeeID" => $payeeId,
                     "FeeStatus" => "This Payee ID is awaiting approval or archived "
                 ]
             ]);
         }*/



        if ($bill->paid == 1) {
            return response()->json([
                "FeeRequest" => [
                    "PayeeName" => $bill->getPropertyList->owner_name ?? 'Owner Name',
                    "PayeeID" => $bill->assessment_no,
                    "Amount" => number_format($bill->bill_amount,2, '.',''),
                    "FeeStatus" => "Assessment Already Paid",
                    "Email" => $bill->email ?? '',
                    "PhoneNumber" => $bill->getPropertyList->owner_gsm ?? '',
                    "KG_TIN"=>$bill->getPropertyList->owner_kgtin ?? '',
                    "ADDRESS"=>$bill->getPropertyList->property_address ?? '',
                ]
            ]);
        }
        /*
         * KG_TIN, EMAIL, PHONE, NAME, ADDRESS
         */
        return response()->json([
            "FeeRequest" => [
                "PayeeName" => $bill->getPropertyList->owner_name ?? '',
                "PayeeID" => $bill->assessment_no,
                "Amount" => number_format(($bill->bill_amount - $bill->paid_amount),2, '.',''),
                "FeeStatus" => "Fee has not yet been paid",
                "Email" => $bill->email ?? '',
                "PhoneNumber" => $bill->getPropertyList->owner_gsm ?? '',
                "KG_TIN"=>$bill->getPropertyList->owner_kgtin ?? '',
                "ADDRESS"=>$bill->getPropertyList->property_address ?? '',
            ]
        ]);
    }


    public function notifyETranzact(Request $request){
        $clientIp = $request->ip();
        $allowedIps = [
            '197.255.244.29',
            '197.255.244.28',
            '197.255.244.5',
            '197.255.244.54',

            //'192.168.1.10',
            //'203.0.113.45',
            '127.0.0.1'
        ];
        if (!in_array($clientIp, $allowedIps)) {
            return response("false -1", 403);
        }

        $receiptNo = $request->RECEIPT_NO;
        $paymentCode = $request->PAYMENT_CODE;
        $transAmount = $request->TRANS_AMOUNT;
        $assessmentNo = $request->CUSTOMER_ID;
        $token = $request->PASSWORD;
        $transRef = $request->RECEIPT_NO ?? 'TEST';
        $transDate = $request->TRANS_DATE ?? now();


        //return response()->json(['token'=>$transAmount],200);
        if (empty($receiptNo)  || empty($transAmount) || empty($assessmentNo)) {
            return  response("false 2");
        }
        $bankName = $request->BANK_NAME;
        $branch = $request->BRANCH_NAME;
        $payMode = "eTranzact";
        $phoneNumber = $request->COL5;
        $customerName = $request->CUSTOMER_NAME;
        $email = $request->COL4;
        $kgTin = $request->COL7;
        //return response()->json(['kgtin'=>$kgTin],200);
        if(!is_numeric($transAmount)){
            return  response("false 4");
        }
        if(!isset($token)){
            return response("Provide token");
        }
        if(isset($token)){
            if($token != $this->eTranzactToken){
                return response("Invalid token");
            }
        }
        $payment = BillPaymentLog::where('receipt_no', $receiptNo)->first();
        if(!empty($payment)){
            return  response("false 1");
        }

        $bill = Billing::where('assessment_no', $assessmentNo)
            ->where('paid', 0)
            ->first();
        if (empty($bill)) {
            return response()->json([
                "FeeRequest" => [
                    "Customer ID" => $assessmentNo,
                    "FeeStatus" => "Invalid Customer ID "
                ]
            ]);
        }else{
            $billList = Billing::where('building_code', $bill->building_code)
                ->where('year','<=', $bill->year)
                ->where('paid', 0)
                ->orderBy('id', 'ASC')
                ->get();
            if(!empty($billList)){
                foreach ($billList as $item){
                    $balance = $item->bill_amount - $item->paid_amount;
                    $transBalance = $transAmount - $balance;
                    if($balance > 0){
                        if($transAmount > $balance){
                            $item->paid_amount += $balance;
                            $item->payment_ref = $paymentCode;
                            $item->paid = 1;
                            $item->date_paid = now();
                            $item->paid_by = 1;
                            $item->save();
                            $this->_registerInPaymentLog($item->id, 5, $balance, $receiptNo, $paymentCode,
                                $assessmentNo, $bankName, $branch, $payMode, $customerName,
                                $email, $kgTin, $transRef, $transDate, $phoneNumber);
                        }
                        else{
                            if($transAmount > 0){
                                $item->paid_amount += $transAmount;
                                $item->payment_ref = $paymentCode;
                                $item->save();
                                if($item->paid_amount == $item->bill_amount){
                                    $item->paid = 1;
                                    $item->date_paid = now();
                                    $item->paid_by = 1;
                                    $item->save();
                                }
                                $this->_registerInPaymentLog($item->id, 5, $transAmount, $receiptNo, $paymentCode,
                                    $assessmentNo, $bankName, $branch, $payMode, $customerName,
                                    $email, $kgTin, $transRef, $transDate, $phoneNumber);
                            }
                        }
                    }
                    $transAmount = $transBalance;
                }
            }
        }
        return  response("true");
        //return response()->json(['message'=>'Payment done'],200);
    }


    private function _registerInPaymentLog($billMasterId, $paidBy = 5, $amount, $receiptNo, $paymentCode,
                                           $assessmentNo, $bankName, $branchName, $payMode, $customerName,
                                           $email, $kgTin, $transRef, $transDate, $phoneNumber){
        $bill = Billing::find($billMasterId);
        if($bill){
            BillPaymentLog::create([
                "bill_master"=>$billMasterId,
                "paid_by"=>$paidBy,
                "amount"=>$amount,
                "receipt_no"=>$receiptNo,
                "payment_code"=>$paymentCode,
                "assessment_no"=>$assessmentNo,

                "building_code"=>!empty($bill) ? $bill->building_code : '',
                "lga_id"=>!empty($bill) ? $bill->lga_id : '',
                "ward"=> $bill->ward ?? '',
                "zone"=> $bill->zone_name ?? '',

                "bank_name"=>$bankName,
                "branch_name"=>$branchName,
                "pay_mode"=>$payMode,
                "customer_name"=>$customerName,
                "email"=>$email,
                "kgtin"=>$kgTin,
                "entry_date"=>Carbon::parse($transDate)->format('Y-m-d'),
                "token"=>$this->eTranzactToken,
                "trans_ref"=>$transRef,
                "reference"=>$transRef,

            ]);



            //update property
            $property = PropertyList::where('building_code', $bill->building_code)->first();
            if(!empty($property)){
                $property->owner_email = $email;
                $property->owner_name = $customerName;
                $property->owner_gsm = $phoneNumber;
                $property->owner_kgtin = $kgTin ?? null;
                $property->save();
            }
            //update owner
            if(!empty($property) && !empty($bill)){
                $owner = Owner::where('kgtin', $kgTin)->first();
                if(empty($owner)){
                    Owner::create([
                        "email"=>$email,
                        "kgtin"=>$kgTin,
                        "name"=>$customerName,
                        "telephone"=>$phoneNumber,
                        "lga_id"=>$bill->lga_id,
                        "added_by"=>$paidBy,
                        "res_address"=>$property->address ?? 'N/A'
                    ]);
                }else{
                    $owner->email = $email;
                    $owner->kgtin = $kgTin;
                    $owner->name = $customerName;
                    $owner->telephone = $phoneNumber;
                    $owner->lga_id = $bill->lga_id;
                    $owner->res_address = $property->address ?? 'N/A';
                    $owner->save();
                }
            }
            //Kogi rems notification register
            KogiRemsNotification::create([
                "assessmentno"=>$bill->assessment_no,
                "buildingcode"=>$bill->building_code,
                "kgtin"=>$kgTin ?? null,
                "name"=>$customerName,
                "amount"=>$amount,
                "phone"=>$phoneNumber,
                "email"=>$email,
                "transdate"=>Carbon::parse($transDate)->format('Y-m-d') ?? now(),
                "transRef"=>$transRef,
                "paymode"=>"eTranzact",
                "bank_name"=>$bankName ?? '',
                "luc_amount"=>$bill->bill_amount,
            ]);

            NotifyKogiRemsJob::dispatch();

        }

    }

}
/*


{
"CUSTOMER_ADDRESS":"Here",
"MERCHANT_CODE":"7006027536",
"CUSTOMER_PHONE_NUMBER":"08030615009",
"TRANS_AMOUNT":"100.0",
"TRANS_DESCR":"KOGI STATE LAND USE CHARGE COLLECTION",
"CUSTOMER_ID":"67DA7F19D0F85",
"TRANS_FEE":"0.0",
"CUSTOMER_NAME":"OLADAYO OLATUNJI",
"TRANS_DATE":"04/24/2025 06:00:16",
"other_info":null,
"customer_email":
"oladayo.olatunji@etranzactng.com",
"SERVICE_ID":"10700",
"BRANCH_CODE":"234",
"PAYMENT_METHOD_NAME":"0",
"BANK_CODE":"777",
"PAYMENT_CODE":"52198de6-468e-4467-be8c-7a6a11290341",
"RECEIPT_NO":"057772341411110700E7B2E9F2C0A7810E",
"TELLER_ID":"14111",
"BANK_NAME":"PocketMoni",
"COL1":"Williams Olufemi",
"COL2":"67DA7F19D0F85",
"COL3":"11955.00",
"COL4":null,
"COL5":"08033046408",
"COL6":"LAND USE CHARGE",
"COL7":"kg/bas/140237",
"COL8":"Marine Road",
"COL9":"null",
"COL10":"null",
"COL11":"null",
"COL12":"PocketMoni",
"COL13":"",
"CHANNEL_NAME":"Bank",
"USERNAME":" ",
"PASSWORD":"ytw6h351ec7897wef89300d240u87n"
}

*/

<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyKogiRemsJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use App\Models\Owner;
use App\Models\PropertyList;
use App\Traits\UtilityTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentValidationController extends Controller
{
    use UtilityTrait;
    public $eTranzactToken = "ytw6h351ec7897wef89300d240u87n";
    public function validatePayment(Request $request)
    {
        $payeeId = $request->query('PAYEE_ID');
        //$paymentType = $request->query('PAYMENT_TYPE');
        if (empty($payeeId)) {
            return  response("PAYEE_ID=$payeeId&~PAYMENT_TYPE=LUC&~FeeStatus=PAYEE ID is Empty", 400);
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
            ],400);
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
                    "PropertyImage"=>$bill->getPropertyList->image ?? '',
                ]
            ]);
        }
        /*
         * KG_TIN, EMAIL, PHONE, NAME, ADDRESS
         */
        $billAmount = ($bill->bill_amount - $bill->paid_amount);
        $bbf = $this->balanceBroughtForward($bill->year, $bill->building_code) ?? 0;
        $grandTotal = $bbf + $billAmount;
        return response()->json([
            "FeeRequest" => [
                "PayeeName" => $bill->getPropertyList->owner_name ?? '',
                "PayeeID" => $bill->assessment_no,
                "Amount" => number_format($grandTotal,2, '.',''),
                "FeeStatus" => "Fee has not yet been paid",
                "Email" => $bill->email ?? '',
                "PhoneNumber" => $bill->getPropertyList->owner_gsm ?? '',
                "KG_TIN"=>$bill->getPropertyList->owner_kgtin ?? '',
                "ADDRESS"=>$bill->getPropertyList->property_address ?? '',
                "PropertyImage"=>$bill->getPropertyList->image ?? '',
            ]
        ]);
    }


    public function notifyETranzact(Request $request){
        try {
            DB::beginTransaction();
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
            $currentYear = date('Y');



            //return response()->json(['token'=>$transAmount],200);
            if (empty($receiptNo)  || empty($transAmount) || empty($assessmentNo)) {
                return  response("false 2");
            }
            $bankName = $request->BANK_NAME;
            $branch = $request->BRANCH_NAME;
            $payMode = "eTranzact";
            $phoneNumber = $request->CUSTOMER_PHONE_NUMBER;
            $customerName = $request->CUSTOMER_NAME;
            $customerAddress = $request->CUSTOMER_ADDRESS ?? 'N/A';
            $email = $request->CUSTOMER_EMAIL ?? 'placeholder@email.com';
            $kgTin = $request->COL7;
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
                $paymentData['payment_code'] = $paymentCode;
                $paymentData['paid_by'] = 5;
                $paymentData['receipt_no'] = $receiptNo;
                $paymentData['payment_ref']  = $paymentCode;
                $paymentData['assessment_no'] = $assessmentNo;
                $paymentData['bank_name'] = $bankName;
                $paymentData['branch'] = $branch;
                $paymentData['pay_mode'] = $payMode;
                $paymentData['customer_name'] = $customerName;
                $paymentData['email'] = $email;
                $paymentData['kg_tin'] = $kgTin;
                $paymentData['trans_ref'] = $transRef;
                $paymentData['trans_date'] = $transDate;
                $paymentData['phone_number'] = $phoneNumber;
                $paymentData['token'] = $token;

                $this->handleBillPayment($bill->building_code, $transAmount, $paymentData);
            }
            DB::commit();
            return  response("true");
        }catch (\Exception $e){
            DB::rollBack();
            return  response("false $e");
        }
    }


    private function _registerInPaymentLog($billMasterId, $paidBy, $amount, $receiptNo, $paymentCode,
                                           $assessmentNo, $bankName, $branchName, $payMode, $customerName,
                                           $email, $kgTin, $transRef, $transDate, $phoneNumber){
        $bill = Billing::find($billMasterId);
        if (!$bill) {
            return;
        }

        $existingLogsCount = BillPaymentLog::where('trans_ref', $transRef)->count();
        $uniqueReceiptNo = $receiptNo;

        if ($existingLogsCount > 0) {
            $uniqueReceiptNo = $receiptNo . '_' . ($existingLogsCount + 1);
        }
        //if($bill){
            BillPaymentLog::create([
                "bill_master"=>$billMasterId,
                "paid_by"=>$paidBy,
                "amount"=>$amount,
                "receipt_no"=>$uniqueReceiptNo,
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

        //}

    }

    public function balanceBroughtForward(int $year, string $buildingCode): float
    {
        return Billing::where('building_code', $buildingCode)
            ->where('year', '<', $year)
            ->where(function ($query) {
                $query->where('paid', 0)
                    ->orWhereColumn('paid_amount', '<', 'bill_amount');
            })
            ->select(DB::raw('SUM(bill_amount - paid_amount) as balance'))
            ->value('balance') ?? 0.0;
    }


    public function createPayment(Request $request)
    {

        $response = Http::withHeaders([
            'clientId' => env('ROUTEPAY_CLIENT_ID'),
            'secretKey' => env('ROUTEPAY_SECRET_KEY'),
            'Content-Type' => 'application/json'
        ])->post('https://apidev.routepay.com/api/payment/initiate', [

            'amount' => $request->amount,

            'customerName' => $request->customerName,

            'customerEmail' => $request->customerEmail,

            'customerPhone' => $request->customerPhone,

            'description' => $request->description,

            'callbackUrl' => route('payment.callback'),

            'redirectUrl' => route('payment.success')
        ]);

        return response()->json($response->json());
    }

    public function verifyPayment($reference)
    {
        $response = Http::withHeaders([
            'clientId' => env('ROUTEPAY_CLIENT_ID'),
            'secretKey' => env('ROUTEPAY_SECRET_KEY'),
        ])->get(
            "https://apidev.routepay.com/api/payment/verify/{$reference}"
        );

        $data = $response->json();

        if (
            $data['httpStatusCode'] == 200 &&
            $data['paymentStatus'] == 0
        ) {

            // Payment successful
        } else {

            // Payment failed
        }
    }

}


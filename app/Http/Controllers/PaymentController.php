<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\Lga;
use App\Models\RoutePayQrRegistration;
use App\Services\RoutePayQrService;
use App\Traits\EmailTrait;
use App\Traits\UtilityTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yabacon\Paystack;

class PaymentController extends Controller
{
    use EmailTrait;
    use UtilityTrait;

    public $appToken = 'b4234351ec7218cadf89300d2402e82b';

    public $selfServiceToken = '55f5ae74fdaeb019f432216cc89f4029';

    public $eTranzactToken = 'ytw6h351ec7897wef89300d240u87n';

    public function handlePaymentRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'mobileNo' => 'required',
            'email' => 'required|email',
            'amount' => 'required',
            'billId' => 'required',
            'paidBy' => 'required',
            'kgtin' => 'nullable|string',
            'appToken' => 'required',
            // 'reference' => 'required|string|unique:bill_payment_logs,reference',
        ], [
            'amount.required' => 'Enter an amount',
            'email.required' => 'Enter a valid email address',
            'billId.required' => '',
            'paidBy.required' => '',
            'email.email' => 'Enter a valid email address',
            'mobileNo.required' => 'Enter mobile number',
            'appToken.required' => 'Token required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $bill = Billing::where('paid', 0)->where('id', $request->billId)->first();
        if ($request->appToken != $this->appToken) {
            return response()->json([
                'message' => "We can't process this request at the moment.",
            ], 404);
        }
        if (empty($bill)) {
            return response()->json([
                'message' => 'Whoops! No record found.',
            ], 404);
        } else {
            $paymentCode = substr(sha1(time()), 31, 40);
            $receiptNo = substr(sha1(time()), 20, 32);

            $paymentData['payment_code'] = $paymentCode;
            $paymentData['paid_by'] = $request->paidBy;
            $paymentData['receipt_no'] = $receiptNo;
            $paymentData['payment_ref'] = $request->transRef;
            // $paymentData['assessment_no'] = $assessmentNo;
            $paymentData['branch'] = 'Credo';
            $paymentData['pay_mode'] = 'Webpay Credo';
            $paymentData['customer_name'] = $request->name;
            $paymentData['email'] = $request->email;
            $paymentData['kg_tin'] = $request->kgtin
                ?? $bill->getPropertyList->owner_kgtin
                ?? '';
            $paymentData['trans_ref'] = $request->transRef ?? '';
            $paymentData['reference'] = $request->reference ?? '';
            $paymentData['trans_date'] = Carbon::parse(now())->format('Y-m-d');
            // $paymentData['phone_number'] = $phoneNumber;
            $paymentData['token'] = $request->appToken;
            $paymentData['entry_date'] = Carbon::parse(now())->format('Y-m-d');
            $paymentData['branch_name'] = 'Credo';
            $paymentData['bank_name'] = 'Credo';

            $this->handleBillPayment($bill->building_code, $request->amount, $paymentData);
        }

        return response()->json(['data' => 'Payment recorded'], 201);

    }

    /**
     * Start RoutePay hosted checkout (SetRequest). Frontend redirects to redirectUrl;
     * payment is recorded when RoutePay calls the webhook.
     */
    public function initiateRoutePayPayment(Request $request, RoutePayQrService $routePayQrService)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'mobileNo' => 'required|string|max:32',
            'email' => 'required|email|max:255',
            'amount' => 'required|numeric|min:1',
            'billId' => 'required|integer',
            'appToken' => 'required|string',
        ], [
            'amount.required' => 'Enter an amount',
            'amount.min' => 'Amount must be at least ₦1',
            'email.required' => 'Enter a valid email address',
            'email.email' => 'Enter a valid email address',
            'billId.required' => 'Bill is required',
            'mobileNo.required' => 'Enter mobile number',
            'appToken.required' => 'Token required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        if ($request->appToken != $this->appToken) {
            return response()->json([
                'message' => "We can't process this request at the moment.",
            ], 403);
        }

        $bill = Billing::with(['getLGA', 'getPropertyList'])
            ->where('id', $request->billId)
            ->first();

        if ($bill === null) {
            return response()->json([
                'message' => 'Whoops! No record found.',
            ], 404);
        }

        if ((int) $bill->paid === 1) {
            return response()->json([
                'message' => 'This bill is already fully paid.',
            ], 422);
        }

        /** @var Lga|null $lga */
        $lga = $bill->getLGA;
        if ($lga === null || ! $lga->hasPaymentGatewayConfiguration()) {
            return response()->json([
                'message' => 'Payment gateway is not configured for this agency. Contact support.',
            ], 422);
        }

        $settings = $lga->routePaySettingsForQr();
        $amount = round((float) $request->amount, 2);
        $property = $bill->getPropertyList;
        $merchantReference = RoutePayQrService::merchantReferenceForBill(
            (string) ($bill->assessment_no ?? ''),
            $bill->year
        );

        $returnUrl = trim((string) ($settings['qr_return_url'] ?? ''));
        if ($returnUrl === '') {
            $returnUrl = Lga::routePayCheckoutReturnUrl($lga->payment_gateway_qr_return_url);
        }

        $fullName = trim((string) $request->name);
        $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
        $customer = [
            'email' => (string) $request->email,
            'mobile' => (string) $request->mobileNo,
            'firstname' => $nameParts[0] ?? 'Customer',
            'lastname' => $nameParts[1] ?? '',
            'username' => (string) $request->email,
        ];

        $productName = RoutePayQrService::billDynamicDisplayName(
            $property->property_name ?? null,
            (string) ($bill->assessment_no ?? '')
        );
        $amountString = RoutePayQrService::amountFormStringForRoutePay($amount);
        $products = [[
            'name' => $productName,
            'unitPrice' => $amountString,
            'quantity' => 1,
        ]];

        $result = $routePayQrService->initiateHostedPayment(
            $amount,
            $merchantReference,
            $returnUrl,
            $customer,
            $products,
            $settings
        );

        if (empty($result['redirect_url'])) {
            return response()->json([
                'message' => $result['response_message'] ?? 'Could not start RoutePay payment. Try again later.',
            ], 502);
        }

        RoutePayQrRegistration::updateOrCreate(
            ['merchant_reference' => $result['merchant_reference']],
            [
                'bill_id' => $bill->id,
                'lga_id' => $bill->lga_id,
                'assessment_no' => $bill->assessment_no,
                'transaction_reference' => $result['transaction_reference'],
                'amount_expected' => $amount,
            ]
        );

        return response()->json([
            'data' => [
                'redirectUrl' => $result['redirect_url'],
                'merchantReference' => $result['merchant_reference'],
                'transactionReference' => $result['transaction_reference'],
            ],
        ], 200);
    }

    public function handlePaymentRequestByAssessmentNo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'mobileNo' => 'required',
            'email' => 'required|email',
            'amount' => 'required',
            'billId' => 'required',
            'paidBy' => 'required',
            'transRef' => 'required',
            'kgtin' => 'nullable|string',
            'appToken' => 'required',
            // 'reference' => 'required|string|unique:bill_payment_logs,reference',
        ], [
            'amount.required' => 'Enter an amount',
            'email.required' => 'Enter a valid email address',
            'billId.required' => '',
            'paidBy.required' => '',
            'transRef.required' => 'Transaction reference is missing',
            'email.email' => 'Enter a valid email address',
            'mobileNo.required' => 'Enter mobile number',
            'appToken.required' => 'Token required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $bill = Billing::where('paid', 0)->where('assessment_no', $request->billId)->first();
        if ($request->appToken != $this->selfServiceToken) {
            return response()->json([
                'message' => "We can't process this request at the moment.",
            ], 404);
        }

        $reference = BillPaymentLog::where('reference', $request->transRef)->first();
        if ($reference) {
            return response()->json([
                'message' => 'Duplicate transaction',
            ], 404);
        }

        if (empty($bill)) {
            return response()->json([
                'message' => 'Whoops! No record found.',
            ], 404);
        } else {
            $paymentCode = substr(sha1(time()), 31, 40);
            $receiptNo = substr(sha1(time()), 20, 32);

            $paymentData['payment_code'] = $paymentCode;
            $paymentData['paid_by'] = $request->paidBy;
            $paymentData['receipt_no'] = $receiptNo;
            $paymentData['payment_ref'] = $request->transRef;
            // $paymentData['assessment_no'] = $assessmentNo;
            $paymentData['branch'] = 'Credo';
            $paymentData['pay_mode'] = 'Webpay Credo';
            $paymentData['customer_name'] = $request->name;
            $paymentData['email'] = $request->email;
            $paymentData['kg_tin'] = $request->kgtin
                ?? $bill->getPropertyList->owner_kgtin
                ?? '';
            $paymentData['trans_ref'] = $request->transRef ?? '';
            $paymentData['reference'] = $request->reference ?? '';
            $paymentData['trans_date'] = Carbon::parse(now())->format('Y-m-d');
            // $paymentData['phone_number'] = $phoneNumber;
            $paymentData['token'] = $request->appToken;
            $paymentData['entry_date'] = Carbon::parse(now())->format('Y-m-d');
            $paymentData['branch_name'] = 'Credo';
            $paymentData['bank_name'] = 'Credo';

            $this->handleBillPayment($bill->building_code, $request->amount, $paymentData);
        }

        return response()->json(['data' => 'Payment recorded'], 201);

    }

    public function processOnlinePayment(Request $request)
    {

        $reference = isset($request->reference) ? $request->reference : '';
        if (! $reference) {
            exit('No reference supplied');
        }
        $paystack = new Paystack(config('app.paystack_secret_key'));
        try {
            // verify using the library
            $tranx = $paystack->transaction->verify([
                'reference' => $reference, // unique to transactions
            ]);
        } catch (Paystack\Exception\ApiException $exception) {
            session()->flash('error', 'Whoops! Something went wrong.');

            return redirect()->route('top-up');
        }
        if ($tranx->data->status === 'success') {
            try {
                // return dd($tranx->data->metadata->cost);
                $transaction_type = $tranx->data->metadata->transaction;
                $account = $tranx->data->metadata->account;
                $category = $tranx->data->metadata->category;
                switch ($transaction_type) {
                    case 4:
                        $branchId = Auth::user()->branch;
                        $defaultCurrency = env('NAIRA_ID');
                        $note = 'Purchase of bulk SMS units. The amount includes convenience fee';
                        $this->bulksmsaccount->creditAccount($reference,
                            $tranx->data->amount, // 50900
                            $tranx->data->metadata->cost, $tranx->data->metadata->user); // cost = 500
                        $this->cashbook->addCashBook($branchId, $category, $account,
                            $defaultCurrency, 2, 0, 2, now(),
                            $note, $note,
                            ($tranx->data->amount + $tranx->data->metadata->cost) / 100, 0,
                            substr(sha1(time()), 31, 40), date('m', strtotime(now())), date('Y', strtotime(now())));
                        break;
                }
                switch ($transaction_type) {
                    case 4:
                        session()->flash('success', 'Your top-up transaction was successful.');

                        return redirect()->route('top-up');
                }
            } catch (Paystack\Exception\ApiException $ex) {

            }

        } // 67C8755BD2579
    }
}

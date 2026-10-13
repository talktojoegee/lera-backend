<?php

namespace App\Traits;

use App\Jobs\NotifyKogiRemsJob;
use App\Jobs\SendBillPaymentReceiptEmailJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use App\Models\Owner;
use App\Models\PropertyList;
use Carbon\Carbon;
use Illuminate\Http\Request;

trait UtilityTrait
{
    public $eTranzactToken = 'ytw6h351ec7897wef89300d240u87n';

    public function generateRandomUrls(Request $request)
    {
        $numberOfUrls = 10;
        $randomUrls = [];

        for ($i = 0; $i < $numberOfUrls; $i++) {
            $randomUrl = $this->generateRandomUrl();
            $randomUrls[] = $randomUrl;
        }

        return response()->json(['urls' => $randomUrls]);
    }

    private function generateRandomUrl()
    {
        $length = 8;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    public function median(array $data): float
    {
        sort($data);
        $count = count($data);
        $mid = floor($count / 2);

        if ($count % 2 === 1) {
            return $data[$mid];
        } else {
            return ($data[$mid - 1] + $data[$mid]) / 2;
        }
    }

    public function convertToSqm($value)
    {
        if (str_ends_with($value, '_Acre')) {
            $number = (int) str_replace('_Acre', '', $value);
            $sqm = $number * 4046.86;

            return $sqm.'_sqm';
        } elseif (str_ends_with($value, '_sqm')) {
            return $value;
        } else {
            return 'Invalid format';
        }
    }

    private function convertLessThanThousand($number, $ones, $teens, $tens)
    {
        $words = '';

        if ($number >= 100) {
            $words .= $ones[intval($number / 100)].' hundred';
            $number = $number % 100;
            if ($number > 0) {
                $words .= ' and ';
            }
        }

        if ($number > 10 && $number < 20) {
            $words .= $teens[$number - 10];
        } else {
            $ten = intval($number / 10);
            $one = $number % 10;
            if ($ten > 0) {
                $words .= $tens[$ten];
                if ($one > 0) {
                    $words .= '-'.$ones[$one];
                }
            } elseif ($one > 0) {
                $words .= $ones[$one];
            }
        }

        return trim($words);
    }

    private function convertDecimal($decimalPart, $ones, $subCurrency)
    {
        if (! $decimalPart || intval($decimalPart) === 0) {
            return '';
        }
        $decimalWords = '';
        foreach (str_split($decimalPart) as $digit) {
            $decimalWords .= $ones[intval($digit)].' ';
        }

        return trim($decimalWords).' '.$subCurrency;
    }

    public function numberToWords($num, $mainCurrency = 'naira', $subCurrency = 'kobo')
    {
        if ($num == 0) {
            return 'zero';
        }

        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        $teens = ['', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', 'ten', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $thousands = ['', 'thousand', 'million', 'billion', 'trillion'];

        // Split number into integer and decimal parts
        $parts = explode('.', strval($num));
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? $parts[1] : null;

        $wordString = '';
        $chunkCount = 0;
        $intNum = intval($integerPart);

        while ($intNum > 0) {
            $chunk = $intNum % 1000;
            if ($chunk > 0) {
                $chunkWords = $this->convertLessThanThousand($chunk, $ones, $teens, $tens);
                if ($chunkCount > 0) {
                    $chunkName = $thousands[$chunkCount] ?? '';
                    $wordString = $chunkWords.' '.$chunkName.($wordString ? ', '.$wordString : '');
                } else {
                    $wordString = $chunkWords.($wordString ? ', '.$wordString : '');
                }
            }
            $intNum = floor($intNum / 1000);
            $chunkCount++;
        }

        if ($decimalPart) {
            $decimalWords = $this->convertDecimal($decimalPart, $ones, $subCurrency);
            $wordString .= $decimalPart ? ' and '.$decimalWords : '';
        }

        return ucfirst(trim($wordString.' '.$mainCurrency));
    }

    /**
     * Apply payment to one bill row (RoutePay webhook / hosted checkout).
     *
     * @return float Amount actually applied (0 when nothing was credited)
     */
    public function handleSingleBillPayment(Billing $bill, float $transAmount, array $paymentData): float
    {
        if ($transAmount <= 0) {
            return 0.0;
        }

        $paidFromLogs = (float) BillPaymentLog::where('bill_master', $bill->id)->sum('amount');
        $paidSoFar = max((float) $bill->paid_amount, $paidFromLogs);
        $balance = (float) $bill->bill_amount - $paidSoFar;

        if ($balance <= 0) {
            return 0.0;
        }

        $amountToPay = min($transAmount, $balance);

        $bill->paid_amount = $paidSoFar + $amountToPay;
        $bill->payment_ref = $paymentData['payment_ref'] ?? null;

        if ($bill->paid_amount >= $bill->bill_amount) {
            $bill->paid = 1;
            $bill->date_paid = now();
            $bill->paid_by = $paymentData['paid_by'] ?? null;
        }

        $bill->save();

        $paymentData['assessment_no'] = $bill->assessment_no;
        $paymentData['zone'] = $bill->zone_name ?? '';

        $this->_registerInPaymentLog(
            $bill->id,
            $paymentData['paid_by'] ?? 5,
            $amountToPay,
            $paymentData['receipt_no'] ?? null,
            $paymentData['payment_ref'] ?? null,
            $paymentData['assessment_no'] ?? null,
            $paymentData['bank_name'] ?? null,
            $paymentData['branch'] ?? null,
            $paymentData['pay_mode'] ?? null,
            $paymentData['customer_name'] ?? null,
            $paymentData['email'] ?? null,
            $paymentData['kg_tin'] ?? null,
            $paymentData['trans_ref'] ?? null,
            $paymentData['trans_date'] ?? null,
            $paymentData['phone_number'] ?? null
        );

        return $amountToPay;
    }

    /**
     * Repair billings.paid_amount / paid when payment logs exist but the bill row was not updated.
     */
    public function syncBillPaidAmountFromPaymentLogs(Billing $bill): Billing
    {
        $paidFromLogs = (float) BillPaymentLog::where('bill_master', $bill->id)
            ->where('assessment_no', '!=', 'DONATION')
            ->where('pay_mode', '!=', 'RoutePay Donation')
            ->sum('amount');

        if ($paidFromLogs > (float) $bill->paid_amount) {
            $bill->paid_amount = $paidFromLogs;
        }

        if ((float) $bill->bill_amount > 0 && (float) $bill->paid_amount >= (float) $bill->bill_amount) {
            $bill->paid = 1;
            $bill->date_paid = $bill->date_paid ?? now();
        }

        $bill->save();

        return $bill->fresh();
    }

    public function handleBillPayment(string $buildingCode, float $transAmount, array $paymentData)
    {
        $currentYear = date('Y');
        $bills = Billing::where('building_code', $buildingCode)
            ->where('year', '<=', $currentYear)
            // ->whereColumn('paid_amount', '<', 'bill_amount')
            ->where('paid', 0)
            ->orderBy('year', 'ASC')
            ->get();

        foreach ($bills as $bill) {

            if ($transAmount <= 0) {
                break;
            }

            $balance = $bill->bill_amount - $bill->paid_amount;

            if ($balance <= 0) {
                continue;
            }

            $amountToPay = min($transAmount, $balance);

            // Update bill
            $bill->paid_amount += $amountToPay;
            $bill->payment_ref = $paymentData['payment_ref'] ?? null;

            if ($bill->paid_amount >= $bill->bill_amount) {
                $bill->paid = 1;
                $bill->date_paid = now();
                $bill->paid_by = $paymentData['paid_by'] ?? null;
            }

            $bill->save();
            $paymentData['assessment_no'] = $bill->assessment_no;
            $paymentData['zone'] = $bill->zone_name ?? '';

            // Register log if needed
            $this->_registerInPaymentLog(
                $bill->id,
                $paymentData['paid_by'] ?? 5,
                $amountToPay,
                $paymentData['receipt_no'] ?? null,
                $paymentData['payment_ref'] ?? null,
                $paymentData['assessment_no'] ?? null,
                $paymentData['bank_name'] ?? null,
                $paymentData['branch'] ?? null,
                $paymentData['pay_mode'] ?? null,
                $paymentData['customer_name'] ?? null,
                $paymentData['email'] ?? null,
                $paymentData['kg_tin'] ?? null,
                $paymentData['trans_ref'] ?? null,
                $paymentData['trans_date'] ?? null,
                $paymentData['phone_number'] ?? null
            );

            $transAmount -= $amountToPay;
        }

    }

    private function _registerInPaymentLog($billMasterId, $paidBy, $amount, $receiptNo, $paymentCode,
        $assessmentNo, $bankName, $branchName, $payMode, $customerName,
        $email, $kgTin, $transRef, $transDate, $phoneNumber)
    {
        $bill = Billing::find($billMasterId);
        if (! $bill) {
            return;
        }

        $existingLogsCount = BillPaymentLog::where('trans_ref', $transRef)->count();
        $uniqueReceiptNo = $receiptNo;

        if ($existingLogsCount > 0) {
            $uniqueReceiptNo = $receiptNo.'_'.($existingLogsCount + 1);
        }

        // if($bill){
        $paymentLog = BillPaymentLog::create([
            'bill_master' => $billMasterId,
            'paid_by' => $paidBy,
            'amount' => $amount,
            'receipt_no' => $uniqueReceiptNo,
            'payment_code' => $paymentCode,
            'assessment_no' => $assessmentNo,

            'building_code' => ! empty($bill) ? $bill->building_code : '',
            'lga_id' => ! empty($bill) ? $bill->lga_id : '',
            'ward' => $bill->ward ?? '',
            'zone' => $bill->zone_name ?? '',

            'bank_name' => $bankName,
            'branch_name' => $branchName,
            'pay_mode' => $payMode,
            'customer_name' => $customerName,
            'email' => $email,
            'kgtin' => $kgTin,
            'entry_date' => Carbon::parse($transDate)->format('Y-m-d'),
            'token' => $this->eTranzactToken,
            'trans_ref' => $transRef,
            'reference' => $transRef,

        ]);

        SendBillPaymentReceiptEmailJob::dispatch($paymentLog->id);

        // update property
        $property = PropertyList::where('building_code', $bill->building_code)->first();
        if (! empty($property)) {
            $property->owner_email = $email;
            $property->owner_name = $customerName;
            $property->owner_gsm = $phoneNumber;
            $property->owner_kgtin = $kgTin ?? null;
            $property->save();
        }
        // update owner
        if (! empty($property) && ! empty($bill)) {
            $owner = Owner::where('kgtin', $kgTin)->first();
            if (empty($owner)) {
                Owner::create([
                    'email' => $email,
                    'kgtin' => $kgTin,
                    'name' => $customerName,
                    'telephone' => $phoneNumber,
                    'lga_id' => $bill->lga_id,
                    'added_by' => $paidBy,
                    'res_address' => $property->address ?? 'N/A',
                ]);
            } else {
                $owner->email = $email;
                $owner->kgtin = $kgTin;
                $owner->name = $customerName;
                $owner->telephone = $phoneNumber;
                $owner->lga_id = $bill->lga_id;
                $owner->res_address = $property->address ?? 'N/A';
                $owner->save();
            }
        }
        // Kogi rems notification register
        KogiRemsNotification::create([
            'assessmentno' => $bill->assessment_no,
            'buildingcode' => $bill->building_code,
            'kgtin' => $kgTin ?? null,
            'name' => $customerName,
            'amount' => $amount,
            'phone' => $phoneNumber,
            'email' => $email,
            'transdate' => Carbon::parse($transDate)->format('Y-m-d') ?? now(),
            'transRef' => $transRef,
            'paymode' => 'eTranzact',
            'bank_name' => $bankName ?? '',
            'luc_amount' => $bill->bill_amount,
        ]);

        NotifyKogiRemsJob::dispatch();

        // }

    }
}

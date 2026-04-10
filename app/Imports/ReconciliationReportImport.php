<?php

namespace App\Imports;

use App\Models\BillPaymentLog;
use App\Models\PropertyList;
use App\Models\Reconciliation;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ReconciliationReportImport implements ToModel, WithStartRow, WithMultipleSheets,WithCalculatedFormulas
{
    public $header, $monthYear, $auth, $masterId;
    public function __construct($header, $monthYear, $auth, $masterId){
        $this->header = $header;
        $this->monthYear = $monthYear;
        $this->auth = $auth;
        $this->masterId = $masterId;
    }
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        //
    }

    public function startRow(): int
    {
        if(isset($this->header)){
            return 2;
        }
        return 1;

    }
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function model(array $row)
    {
        if (empty(array_filter($row))) {
            return null; // Skip empty rows
        }

        try {
            $entryDateSerial = $row[0];
            $unixDate = ($entryDateSerial - 25569) * 86400;
            $entryDate = date('Y-m-d', $unixDate);

            $valueDateSerial = $row[2];
            $unixDate = ($valueDateSerial - 25569) * 86400;
            $valueDate = date('Y-m-d', $unixDate);

            $credit = floatval(preg_replace('/[^\d.]/', '', $row[4]));
            $debit = floatval(preg_replace('/[^\d.]/', '', $row[3]));
            [$assessmentNo, $payerName] = $this->extractDetails((string)$row[1]);
            $matched = false;
            $assessmentNoFlag = false;
            $creditFlag = false;
            $property = null;
            $reason = null;
            $billPaymentLog =  BillPaymentLog::where('assessment_no', $assessmentNo)
                ->where('reconciled', 0)
                ->first();
            $payment =  BillPaymentLog::where('assessment_no', $assessmentNo)
                ->where('amount',$credit)
                ->where('matched', 0)
                ->where('reconciled', 0)->first();
            if($payment){
                $creditFlag = true;
                $assessmentNoFlag = true;
            }
            if ( !empty($billPaymentLog)) {
                if ($creditFlag) {
                    $reason = "Match found!";
                } else {
                    $reason = "Assessment number found! Amount not found!";
                }
            } else {
                $reason = "Assessment number not found!";
            }

            if( $assessmentNoFlag && $creditFlag ) {
                $property = PropertyList::where('building_code', $billPaymentLog->building_code)->first();
            }

           $record =  Reconciliation::create([
                "entry_date"=>$entryDate,
                "details"=>$row[1],
                "value_date"=>$valueDate,
                "debit"=>0,
                "credit"=>0,
                "bank_amount"=>$credit > 0 ? $credit : $debit,
                "balance"=>$row[5],
                "user_id"=>$this->auth,
                "month_year"=>$this->monthYear,
                "month"=>date("m",strtotime($this->monthYear)),
                "year"=>date("Y",strtotime($this->monthYear)),
                "payer_name"=> $payerName ?? 'N/A',
                "assessment_no"=>  $assessmentNo ?? 'N/A' ,
                "reconciled"=> 0,
                "reason"=>$reason,
                "master_id"=>$this->masterId,
                "building_code"=> $creditFlag ? $property->building_code : 'N/A',
                "property_name"=> $creditFlag ? $property->property_name : 'N/A',
                "dc_type"=>$credit > 0 ? 2 : 1,
                "matched"=> $creditFlag ? 1 : 0,
               "bill_payment_assessment_no"=>!empty($billPaymentLog) ? $billPaymentLog->assessment_no : 'N/A',
               "billing_amount"=>!empty($billPaymentLog) ? $billPaymentLog->amount : 0,
               "bill_payment_building_code"=>!empty($billPaymentLog) ? $billPaymentLog->building_code : 'N/A',
            ]);
        if($creditFlag){
            $payment->matched = 1;
            $payment->reconcile_detail_id = $record->id;
            $payment->save();
        }
        } catch (\Exception $e) {
            Log::error("Import error: " . $e->getMessage());
        }
    }

    public function extractDetails($details)
    {
        if (empty($details) || !is_string($details)) {
            return [null, null];
        }

        $original = $details;
        $details = strtoupper(trim($details));
        preg_match('/(?:^|[^A-Z0-9])([A-Z0-9]{13})(?:[^A-Z0-9]|$)/', $details, $matches);
        $assessmentNo = $matches[1] ?? null;

        if (!$assessmentNo) {
            return [null, null];
        }
        $name = str_ireplace($assessmentNo, '', $original);
        $prefixes = [
            'PAYOUTLET-', 'PAYOUTLET_', 'BANK-', 'WEB-', 'CREDO:;WEB-',
            'PAYOUTLET', 'Payoutlet-', 'payoutlet-', 'PAYOUTLET:;', 'PAYOUTLET:', 'CREDO:;WEB'
        ];
        foreach ($prefixes as $prefix) {
            $name = str_ireplace($prefix, '', $name);
        }
        $name = preg_replace('/[-_:;]+/', ' ', $name);
        $name = trim($name);

        return [strtoupper($assessmentNo), $name ?: null];
    }







}

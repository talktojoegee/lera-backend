<?php

namespace App\Imports;

use App\Models\BillPaymentLog;
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
            $credit = floatval(preg_replace('/[^\d.]/', '', $row[4]));
            if ($credit > 0){
                $entryDateSerial = $row[0];
                $unixDate = ($entryDateSerial - 25569) * 86400;
                $entryDate = date('Y-m-d', $unixDate);

                $valueDateSerial = $row[0];
                $unixDate = ($valueDateSerial - 25569) * 86400;
                $valueDate = date('Y-m-d', $unixDate);
                [$assessmentNo, $payerName] = $this->extractDetails($row[1]);
                if(!is_null($assessmentNo)){
                    $billPaymentLog =  BillPaymentLog::where('assessment_no', $assessmentNo)->where('amount', $credit)->first();
                    if(!empty($billPaymentLog)){
                        Reconciliation::create([
                            "entry_date"=>$entryDate,
                            "details"=>$row[1],
                            "value_date"=>$valueDate,
                            "debit"=>$row[3],
                            "credit"=>$row[4],
                            "balance"=>$row[5],
                            "user_id"=>$this->auth,
                            "month_year"=>$this->monthYear,
                            "month"=>date("m",strtotime($this->monthYear)),
                            "year"=>date("Y",strtotime($this->monthYear)),
                            "payer_name"=>$payerName,
                            "assessment_no"=>$assessmentNo,
                            "reconciled"=>1,//matched
                            "reason"=>"Match found!",
                            "master_id"=>$this->masterId,
                            "building_code"=>$billPaymentLog->building_code,
                        ]);
                    }else{
                        Reconciliation::create([
                            "entry_date"=>$entryDate,
                            "details"=>$row[1],
                            "value_date"=>$valueDate,
                            "debit"=>$row[3],
                            "credit"=>$row[4],
                            "balance"=>$row[5],
                            "user_id"=>$this->auth,
                            "month_year"=>$this->monthYear,
                            "month"=>date("m",strtotime($this->monthYear)),
                            "year"=>date("Y",strtotime($this->monthYear)),
                            "payer_name"=>$payerName,
                            "assessment_no"=>$assessmentNo,
                            "reconciled"=>0,//mis-matched
                            "reason"=>"Could not find either matching assessment no or amount!",
                            "master_id"=>$this->masterId,
                        ]);
                    }

                }

               // }
            }

        } catch (\Exception $e) {
            Log::error("Import error: " . $e->getMessage());
        }
    }

    private function extractDetails0($details) {
        $details = strtoupper($details);
        $prefixes = ['PAYOUTLET-', 'PAYOUTLET_', 'BANK-', 'WEB-', 'CREDO:;WEB-'];

        foreach ($prefixes as $prefix) {
            if (strpos($details, $prefix) !== false) {
                // Remove the prefix
                $parts = explode($prefix, $details, 2);
                if (count($parts) < 2) return [null, null];
                $afterPrefix = $parts[1];

                // Split by dash or underscore, whichever comes next
                if (strpos($afterPrefix, '-') !== false) {
                    $segments = explode('-', $afterPrefix, 2);
                } elseif (strpos($afterPrefix, '_') !== false) {
                    $segments = explode('_', $afterPrefix, 2);
                } else {
                    return [null, null];
                }

                $assessmentNo = trim($segments[0]);
                $customerName = isset($segments[1]) ? trim($segments[1]) : null;

                return [$assessmentNo, $customerName];
            }
        }

        return [null, null];
    }

    private function extractDetails($details) {
        $details = strtoupper(trim($details));

        // First, try to match assessment number pattern
        preg_match_all('/[A-Z0-9]{12,}/', $details, $matches);
        $assessmentNo = $matches[0][0] ?? null;

        // Now extract name by removing the assessment no and known prefixes
        $name = $details;

        // Remove known prefixes
        $prefixes = ['PAYOUTLET-', 'PAYOUTLET_', 'BANK-', 'WEB-', 'CREDO:;WEB-', 'PAYOUTLET '];
        foreach ($prefixes as $prefix) {
            if (strpos($name, $prefix) !== false) {
                $name = str_replace($prefix, '', $name);
            }
        }

        // Remove assessment number
        if ($assessmentNo) {
            $name = str_replace($assessmentNo, '', $name);
        }

        // Clean up leftover dashes, underscores, or colons
        $name = preg_replace('/[-_:]+/', ' ', $name);
        $name = trim($name);

        return [$assessmentNo, $name];
    }


}

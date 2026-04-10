<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use Illuminate\Console\Command;
use League\Csv\Reader;

class LoadDiscountedBillsExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:loadDiscountAllowed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

         $csv = Reader::createFromPath(public_path('/storage/KSLASDiscount.csv'), 'r');
         $csv->setHeaderOffset(0);
          $records = $csv->getRecords();

          $counter = 0;
         foreach ($records as  $record) {
             //return dd($record['BillAmount']);
             $billMaster = Billing::where('assessment_no', $record['AssessmentNo'])->first();
             if(!empty($billMaster)){
                 $discountedAmount = (float) str_replace(',', '', $record['DiscountAllowed']);
                 BillPaymentLog::create([
                     "bill_master"=>$billMaster->id,
                     "paid_by"=>5,
                     "amount"=>$discountedAmount,
                     "trans_ref"=>"DISCOUNT_".$record['AssessmentNo'],
                     "reference"=>"DISCOUNT_".$record['AssessmentNo'],
                     "receipt_no" => "RECEIPT_".$billMaster->id,
                     "payment_code"=>"DISCOUNT",
                     "assessment_no"=>$record['AssessmentNo'],
                     "bank_name"=>null,
                     "branch_name"=>null,
                     "pay_mode"=>"DISCOUNT",
                     "customer_name"=>$record['Owner'],
                     "email"=>"placeholder@gmail.com",
                     "kgtin"=>$record['KGTIN'],
                     "building_code"=>$record['BuildingCode'],
                     "ward"=>$billMaster->ward,
                     "zone"=>$record['Zone'],
                     "lga_id"=>$billMaster->lga_id,
                     "entry_date"=>'2025-12-31',
                     "token"=>"DISCOUNT_TOKEN_".$record['AssessmentNo'],
                     "is_discount"=>1,
                     "discount"=>$discountedAmount,
                 ]);
                 $counter++;
                 echo "Discounted Bill Added: ".$record['AssessmentNo']." Item Number: ". $counter."\n";
             }

         }
         echo "Total of $counter records inserted!";


    }
}

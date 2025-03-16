<?php

namespace App\Console\Commands;

use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use Illuminate\Console\Command;
use League\Csv\Reader;

class BillingCodeSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billingcode:command';

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
        $csv = Reader::createFromPath(public_path('/storage/billing-code-setup.csv'), 'r');
        $csv->setHeaderOffset(0);
         $records = $csv->getRecords();
        foreach ($records as  $record) {

            //return dd($record);
            PropertyAssessmentValue::create([
                "property_use"=>$record['PROPERTY USE'],
                "pav_code" => $record['BILLING CODE'],
                "zones"=>$record['ZONE'],
                "class_id"=>$this->getPropertyClassByName($record['PROPERTY CATEGORY'])->id ?? 1,
                "lr"=>str_replace(",","",$record['LR (IN NAIRA/SQM)']),
                "ba"=>$record['BA (IN PERCENTAGE)'],
                "rr"=>$record['RR'],
                "br"=>$record['BR (IN NAIRA/SQM)'],
                "sync_word"=>$record['SYNC WORD'],
            ]);
        }
    }

    public function getPropertyClassByName($className){
        return PropertyClassification::whereRaw('LOWER(class_name) = LOWER(?)', [$className])->first();
    }

}

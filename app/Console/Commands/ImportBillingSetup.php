<?php

namespace App\Console\Commands;

use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportBillingSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:billSetup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import billing setup details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
            $path =  storage_path('app/public/update-billing.csv');
            $reader =  Reader::createFromPath($path, 'r');
            $reader->setDelimiter(",");
            foreach ($reader->getRecords() as $key => $row) {
                /*if($file->heading == 1){
                    if($key == 0){
                        continue;
                    }
                }*/
                $none = [];
                $class = PropertyClassification::where("class_name", 'like', '%' . $row[2] . '%')->first();
                if(!empty($class)){
                    PropertyAssessmentValue::create([
                        //"assessed_amount"=>$row[0],
                        //"value_rate"=>$row[0],
                        "property_use"=>$row[3],
                        "pav_code"=>$row[0],
                        "zones"=>$row[1],
                        "class_id"=>$class->id,
                        "lr"=>$row[5],
                        "ba"=>$row[6],
                        "rr"=>$row[7],
                        "br"=>$row[4],
                    ]);

                    //return dd($class); //category
                }else{
                    array_push($none, $row[2]);
                }


            }
            print_r($none);
    }
}

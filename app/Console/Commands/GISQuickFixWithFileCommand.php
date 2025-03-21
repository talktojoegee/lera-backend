<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class GISQuickFixWithFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:gisfile';

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
        $path =  storage_path('app/public/delete-insert.csv');
        $reader =  Reader::createFromPath($path, 'r');
        $reader->setDelimiter(",");
        $first = true;
        $counter = 0;
        $updateCounter = 0;
        /*foreach ($reader->getRecords() as $key => $row) {
            if ($first) {
                $first = false;
                continue;
            }
            $record = DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                ->where('completeness_status', 'Complete')
                ->where('bill_sync', 1)
                ->where('prop_id', $row[1])
                ->first();

            if(!empty($record)){
                $property = PropertyList::where('building_code', $row[1])->first();
                if(!empty($property)){
                   // $charge = $this->_getChargeRate($record->occupier_s, $record->landuse);
                    $property->ba = $row[5];// $record->compute_area_from_bfp ?? 200;
                    //$property->cr = $charge->id ?? 1;
                    $property->save();
                    $counter++;
                    echo "Item:: $counter \n";
                }
            }
            echo $key.":: Property ID:: $row[1] \n";
        }*/


        //Another operation
        foreach ($reader->getRecords() as $key => $row) {
            if ($first) {
                $first = false;
                continue;
            }

            $bill = Billing::where('building_code', $row[1])->first();
            if(!empty($bill)){
                $bill->delete();
                $counter++;
                echo "Item:: ".$counter." deleted \n";
            }
            $property = PropertyList::where('building_code', $row[1])->first();
            if(!empty($property)){
                $property->ba = $row[3];
                $property->save();
                $updateCounter++;
                echo "Item:: ".$updateCounter." updated \n";
            }

            //echo "Item:: ".$counter;

        }

    }





    public function _getChargeRate($occupier, $landUse){
        $normalizedClassName = trim($occupier);
        switch($landUse){
            case 1: //
                return ChargeRate::first();
            case 2:
            case 5:
            case 6:
                return ChargeRate::find(7);
            case 3:
                return ChargeRate::find(4);
            case 4:
            case 7:
            case 8:
            case 9:
                return ChargeRate::find(3);
            case 10:

            case 11:
                return ChargeRate::find(9);
            case 12:
                return ChargeRate::find(8);
        }

    }
}

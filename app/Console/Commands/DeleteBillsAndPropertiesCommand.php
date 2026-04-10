<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\PropertyList;
use Illuminate\Console\Command;
use League\Csv\Reader;

class DeleteBillsAndPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-bills-properties-command';

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
        $csv = Reader::createFromPath(storage_path('app/public/DeletePropertiesBills.csv'), 'r');
        $csv->setHeaderOffset(0);
        //$records = $csv->getRecords();
        // Convert iterator to array so we can count records
        $records = iterator_to_array($csv->getRecords());
        $total = count($records);

        $this->info("Processing {$total} records...");

        $progressBar = $this->output->createProgressBar($total);
       $progressBar->start();

        $billsDeletedCounter = 0;
        $propertiesDeletedCounter = 0;
        foreach ($records as  $record) {
            $billMaster = Billing::where('building_code', $record['BuildingCode'])->first();
            if(!empty($billMaster)){
                $billMaster->delete();
                $billsDeletedCounter++;
                echo "Bill deleted: ".$record['BuildingCode']." \n";
            }

            //property
            $properties = PropertyList::where('building_code', $record['BuildingCode'])->first();
            if(!empty($properties)){
                $properties->delete();
                $propertiesDeletedCounter++;
                echo "Property deleted: ".$record['BuildingCode']." \n";;
            }
            // Advance progress bar
            $progressBar->advance();

        }
        $progressBar->finish();

        $this->newLine(2);
        echo "Total bills deleted: $billsDeletedCounter \n";
        echo "Total properties deleted: $propertiesDeletedCounter \n";


    }
}

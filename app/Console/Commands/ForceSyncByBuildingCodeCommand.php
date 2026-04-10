<?php

namespace App\Console\Commands;


use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ForceSyncByBuildingCodeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:force-sync-by-building-code-command';

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
        $csv = Reader::createFromPath(storage_path('app/public/distribution_code_null_lluucc_db.csv'), 'r');
        $csv->setHeaderOffset(0);
        $records = iterator_to_array($csv->getRecords());
        $total = count($records);

        $this->info("Processing {$total} records...");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $counter = 0;
        foreach ($records as  $record) {
            $property = PropertyList::where('building_code', $record['building_code'])->first();
            if(!empty($property)){
                $remoteRecord = DB::connection('pgsql')
                    ->table('Land_Admin_New_Form')
                    ->first();
                if(!empty($remoteRecord)){
                    $property->distribution_code = $remoteRecord->distribution_code;
                    $property->save();
                    echo "Distribution code updated: ".$record['building_code']." \n";
                    $counter++;
                }
                echo "Unto the next: ". "\n";
            }
            // Advance progress bar
            $progressBar->advance();

        }
        $progressBar->finish();

        $this->newLine(2);
        echo "Total properties updated: $counter \n";


    }
}

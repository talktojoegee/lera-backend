<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QuickFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quick:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    public $lgaId;
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->lgaId = 0;
        /*$properties = PropertyList::all();
        foreach($properties as $property){
            $bill = Billing::where('building_code', $property->building_code)->first();
            if(!empty($bill)){
                $bill->ward = $property->ward;
                $bill->save();
            }
        }*/
        DB::connection('pgsql')
            ->table('Land_Admin_New_Form')
            ->when($this->lgaId > 0, function($query) {
                return $query->where('lga_id', $this->lgaId);
            })
            ->where('completeness_status', 'Complete')
            ->where('bill_sync', 0)
            //->whereIn('prop_id',["Kg/LKJ/214970", "Kg/LKJ/214980", "Kg/LKJ/214930", "Kg/LKJ/214960", "Kg/LKJ/214940", "Kg/LKJ/523711"])
            ->orderBy('id')
            ->cursor()
            ->each(function($record){
                $classIds = PropertyClassification::pluck('id')->toArray();
                //$classID = in_array($record->landuse, $classIds) ? $record->landuse : 1;
                $filePath = storage_path('logs/landuse_log.txt');
                if(in_array($record->landuse, $classIds)){
                    $message =  $record->landuse." YES \n";
                    file_put_contents($filePath, $message, FILE_APPEND);
                }else{
                    $message =  $record->landuse." NO \n";
                    file_put_contents($filePath, $message, FILE_APPEND);
                }

            });
    }
}


//https://kslas.s3.amazonaws.com//uploads//salamatu%20lsah_10_ps9vq.jpg?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAX4AFLWRHOQD4DN5K%2F20250127%2Fus-east-2%2Fs3%2Faws4_request&X-Amz-Date=20250127T133041Z&X-Amz-Expires=3600&X-Amz-SignedHeaders=host&X

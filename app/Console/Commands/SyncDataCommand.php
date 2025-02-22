<?php

namespace App\Console\Commands;

use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:syncData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize data from a remote server in chunks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //$lga = $this->argument('lga');
        //Log::info("Starting synchronization for LGA: {$lga}");
        DB::connection('pgsql')
            ->table('Land_Admin_New_Form')
            //->where('lga', $name)
            ->orderBy('id')
            ->chunk(1000, function ($records) use (&$counter) {
                foreach ($records as $record) {
                    PropertyList::create([
                        'address'=>$record->street_nam,
                        'area'=>'TEST',//str_replace("_sqm", "", $areaVal),
                        'borehole'=>$record->water == 'Yes' ? 1 : 0,
                        'building_code'=>$record->prop_id,
                        'image'=>$record->photo_link,
                        'owner_email'=>$record->owner_emai,
                        'owner_gsm'=>$record->owner_phon,
                        'owner_kgtin'=>$record->kgtin,
                        'owner_name'=>$record->prop_owner,
                        'title'=>$record->land_status,
                        'pav_code'=> '$pavRecord',
                        'power'=>$record->power == 'Yes' ? 1 : 0,
                        'storey'=> '',
                        'water'=>$record->water == 'Yes' ? 1 : 0,
                        'zone_name'=>'$zoneChar',
                        'sub_zone'=>'A1',//$record->zone, //'A1','B2'
                        'class_name'=> 1 ,//$record["Bld_Cat,
                        'occupant'=>'$record->prop_owner',
                        'building_age'=>$record->property_age,
                        'pay_status'=>null,//$record["Pay_Status"],
                        'lga_id'=>12,//$lgaOne->id ?? null,
                        'class_id'=>1,//$record->landuse,
                        'sync_word'=>'$syncWord',
                        'property_use'=>$propertyUse->property_use ?? null,
                        'cr'=>$chargeRate->id ?? 1,
                        'actual_age'=>$record->property_age,
                        'longitude'=>$record->longitude,
                        'latitude'=>$record->latitude,
                        'property_name'=>$record->prop_name,
                        'occupier'=>$record->occupier_s,
                        'property_address'=>$record->prop_addre,
                        'dep_id'=> 1
                    ]);
                    $counter++;
                }
            });
    }
}

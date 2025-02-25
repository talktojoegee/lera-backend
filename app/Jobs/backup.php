<?php

namespace App\Jobs;

use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyException;
use App\Models\PropertyList;
use App\Models\Zone;
use App\Traits\UtilityTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncDataJob implements ShouldQueue
{
    use Queueable;
    use UtilityTrait;
    public $lgaId;

    /**
     * Create a new job instance.
     */
    public function __construct($lga)
    {
        $this->lgaId = $lga;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $lga = Lga::find($this->lgaId);
            if(empty($lga) && $this->lgaId != 0){
                //return response()->json(['data'=>"Whoops! Something went wrong."],401);
            }
            $addedCount = 0;
            $rejectedCount = 0;

            /*  if($this->lgaId == 0){
                  $data = $this->_fetchAllBuildings();
              }else{
                  $data =  $this->_fetchBuildingsByLGAName($lga->lga_name);
              }
              if(count($data['data']) <= 0){
                  return response()->json(['data'=>"Whoops! Nothing to synchronize"],401);
              }*/
            $counter = 0;

            DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                ->when($this->lgaId > 0, function($query) use ($lga){
                    return $query->where('lga_id', $this->lgaId);
                })
                ->where('completeness_status', 'Complete')
                ->orderBy('id')
                ->chunkById(1000, function ($records) use (&$counter)  {
                    foreach ($records as $record) {
                        $lgaName = trim($record->lga);
                        $lgaOne = Lga::where('lga_name', 'LIKE', "%{$lgaName}%")->first();
                        //Log::info('Searching LGA Name:', ['lga' => $record->lga]);
                        //$lgaOne = Lga::where('lga_name',$record->lga)->first();
                        $propertyList = PropertyList::where("building_code", $record->prop_id)->first();
                        $propertyClassification = PropertyClassification::find($record->landuse);
                        $zoneOne = Zone::where("sub_zone", $record->zone)->first();
                        //if (!empty($lgaOne)  && !empty($zoneOne)) {
                        if (empty($propertyList)) {
                            $syncWord = null;
                            if(!is_null($record->residentia)){
                                $syncWord = $record->residentia;
                            }else if(!is_null($record->commercial)){
                                $syncWord = $record->commercial;
                            }else if(!is_null($record->industrial)){
                                $syncWord = $record->industrial;
                            }else if(!is_null($record->industri_1)){
                                $syncWord = $record->industri_1;
                            }else if(!is_null($record->education)){
                                $syncWord = $record->education;
                            }else if(!is_null($record->agricultur)){
                                $syncWord = $record->agricultur;
                            }else if(!is_null($record->transport)){
                                $syncWord = $record->transport;
                            }else if(!is_null($record->utility)){
                                $syncWord = $record->utility;
                            }else if(!is_null($record->kgsg_publi)){
                                $syncWord = $record->kgsg_publi;
                            }else if(!is_null($record->fgn_public)){
                                $syncWord = $record->fgn_public;
                            }else if(!is_null($record->religious)){
                                $syncWord = $record->religious;
                            }else if(!is_null($record->others)){
                                $syncWord = $record->others;
                            }

                            $zoneChar = $this->_getZoneCharacter($record->zone) ?? 'Z';
                            $chargeRate = $this->_getChargeRate($record->occupier_s);
                            $dep = Depreciation::where('range', $record->property_age)->first();
                            $pavRecord = $this->_getPavCode($record->landuse, $record->zone, $syncWord);
                            $areaVal = $this->convertToSqm($record->property_area);
                            $propertyUse = PropertyAssessmentValue::where('sync_word', $syncWord)->first();


                            $classIds = PropertyClassification::pluck('id')->toArray();
                            $lgaIds = Lga::pluck('id')->toArray();
                            $lgaID = in_array($lgaOne->id, $lgaIds) ? $lgaOne->id : 1;
                            $classID = in_array($record->landuse, $classIds) ? $record->landuse : 1;
                            //34764
                            $lgaExist = Lga::find($lgaOne->id);
                            /*$allFilePath = 'logs/property_all.txt';
                            $message = $record->prop_id . "\t".$record->zone."\t".$record->landuse."\t".$record->lga;
                            Storage::append($allFilePath, $message);*/

                            //if (empty($pavRecord)) {
                            if (empty($lgaOne)  || empty($zoneOne) || empty($propertyClassification) || empty($propertyUse)) {
                                //echo "Item:: ".$counter++." \n";
                                /*$exceptionFilePath = 'logs/property_exception.txt';
                                $message = $record->prop_id . "\t".$record->zone."\t".$record->lga;
                                Storage::append($exceptionFilePath, $message);*/
                                PropertyException::create([
                                    'address'=>$record->street_nam,
                                    'area'=>str_replace("_sqm", "", $areaVal),
                                    //'area'=>$record["Bld_area,
                                    'borehole'=>$record->water == 'Yes' ? 1 : 0,
                                    'building_code'=>$record->prop_id,
                                    'image'=>$record->photo_link,
                                    'owner_email'=>$record->owner_emai,
                                    'owner_gsm'=>$record->owner_phon,
                                    'owner_kgtin'=>$record->kgtin,
                                    'owner_name'=>$record->prop_owner,
                                    'title'=>$record->land_status,
                                    'pav_code'=>  null,
                                    'power'=>$record->power == 'Yes' ? 1 : 0,
                                    //'refuse'=>$record["Street"],
                                    //'size'=>$record["Street"],
                                    'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                                    //'title'=>$record["Street"],
                                    'water'=>$record->water == 'Yes' ? 1 : 0,
                                    'zone_name'=>$zoneChar ?? 'A',
                                    'sub_zone'=>$record->zone ?? 'A1', //'A1','B2'
                                    'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                                    'occupant'=>$record->prop_owner,
                                    'building_age'=>$record->property_age,
                                    'pay_status'=>null,//$record["Pay_Status"],
                                    'lga_id'=>!empty($lgaExist) && isset($lgaExist->id) ? $lgaExist->id : 1,
                                    //'special'=>rand(0,1),
                                    'class_id'=> $classID, //!empty($record->landuse) ? $record->landuse : 1,
                                    //'class_id'=>$propertyClassification->id ?? null,
                                    'cr'=>$chargeRate->id ?? 1,
                                    //'occupier_s'=>$record['occupier_s ?? '',
                                    'actual_age'=>$record->property_age,
                                    'longitude'=>$record->longitude,
                                    'latitude'=>$record->latitude,
                                    'property_name'=>$record->prop_name,
                                    'occupier'=>$record->occupier_s,
                                    'property_address'=>$record->prop_addre,
                                    'sync_word'=>$syncWord,
                                    'property_use'=>$propertyUse->property_use ?? null,
                                    'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                                ]);
                            }else{
                                if(!empty($pavRecord)){
                                    /*$listFilePath = 'logs/property_list.txt';
                                    $message = $record->prop_id . "\t".$record->zone."\t".$record->lga;
                                    Storage::append($listFilePath, $message);*/

                                    //echo "List LGA:: ".$record->lga." \n";
                                    PropertyList::create([
                                        'address'=>$record->street_nam,
                                        'area'=>str_replace("_sqm", "", $areaVal),
                                        //'area'=>$record["Bld_area,
                                        'borehole'=>$record->water == 'Yes' ? 1 : 0,
                                        'building_code'=>$record->prop_id,
                                        'image'=>$record->photo_link,
                                        'owner_email'=>$record->owner_emai,
                                        'owner_gsm'=>$record->owner_phon,
                                        'owner_kgtin'=>$record->kgtin,
                                        'owner_name'=>$record->prop_owner,
                                        'title'=>$record->land_status,
                                        'pav_code'=> $pavRecord->pav_code ?? null,
                                        'power'=>$record->power == 'Yes' ? 1 : 0,
                                        //'refuse'=>$record["Street"],
                                        //'size'=>$record["Street"],
                                        'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                                        //'title'=>$record["Street"],
                                        'water'=>$record->water == 'Yes' ? 1 : 0,
                                        'zone_name'=>$zoneChar ?? 'A',
                                        'sub_zone'=>$record->zone ?? 'A1', //'A1','B2'
                                        'occupant'=>$record->prop_owner,
                                        'building_age'=>$record->property_age,
                                        'pay_status'=>null,//$record["Pay_Status"],
                                        'lga_id'=>!empty($lgaOne) && isset($lgaOne->id) ? $lgaOne->id : 1,//$lgaOne->id ?? 1,
                                        //'special'=>rand(0,1),
                                        'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                                        'class_id'=>$classID,//$record->landuse ?? 1,
                                        //'class_id'=>$propertyClassification->id ?? null,
                                        'sync_word'=>$syncWord,
                                        'property_use'=>$propertyUse->property_use ?? null,
                                        'cr'=>$chargeRate->id ?? 1,
                                        'actual_age'=>$record->property_age,
                                        'longitude'=>$record->longitude,
                                        'latitude'=>$record->latitude,
                                        'property_name'=>$record->prop_name,
                                        'occupier'=>$record->occupier_s,
                                        'property_address'=>$record->prop_addre,
                                        'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                                    ]);
                                    //$counter++;
                                }else{
                                    PropertyException::create([
                                        'address'=>$record->street_nam,
                                        'area'=>str_replace("_sqm", "", $areaVal),
                                        //'area'=>$record["Bld_area,
                                        'borehole'=>$record->water == 'Yes' ? 1 : 0,
                                        'building_code'=>$record->prop_id,
                                        'image'=>$record->photo_link,
                                        'owner_email'=>$record->owner_emai,
                                        'owner_gsm'=>$record->owner_phon,
                                        'owner_kgtin'=>$record->kgtin,
                                        'owner_name'=>$record->prop_owner,
                                        'title'=>$record->land_status,
                                        'pav_code'=>  null,
                                        'power'=>$record->power == 'Yes' ? 1 : 0,
                                        //'refuse'=>$record["Street"],
                                        //'size'=>$record["Street"],
                                        'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                                        //'title'=>$record["Street"],
                                        'water'=>$record->water == 'Yes' ? 1 : 0,
                                        'zone_name'=>$zoneChar ?? 'A',
                                        'sub_zone'=>$record->zone ?? 'A1', //'A1','B2'
                                        'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                                        'occupant'=>$record->prop_owner,
                                        'building_age'=>$record->property_age,
                                        'pay_status'=>null,//$record["Pay_Status"],
                                        'lga_id'=>!empty($lgaExist) && isset($lgaExist->id) ? $lgaExist->id : 1,
                                        //'special'=>rand(0,1),
                                        'class_id'=> $classID, //!empty($record->landuse) ? $record->landuse : 1,
                                        //'class_id'=>$propertyClassification->id ?? null,
                                        'cr'=>$chargeRate->id ?? 1,
                                        //'occupier_s'=>$record['occupier_s ?? '',
                                        'actual_age'=>$record->property_age,
                                        'longitude'=>$record->longitude,
                                        'latitude'=>$record->latitude,
                                        'property_name'=>$record->prop_name,
                                        'occupier'=>$record->occupier_s,
                                        'property_address'=>$record->prop_addre,
                                        'sync_word'=>$syncWord,
                                        'property_use'=>$propertyUse->property_use ?? null,
                                        'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                                    ]);
                                    $noneFilePath = 'logs/property_none.txt';
                                    $message = $record->prop_id . "\t".$record->zone."\t".$record->lga;
                                    Storage::append($noneFilePath, $message);
                                }
                            }
                        }
                        // }
                        $counter++;
                        echo "Item Number:: ".$counter++." \n";
                    }
                });

            // Send success email
            //Mail::to('admin@example.com')->send(new SyncStatusMail('Data synchronization completed successfully!'));
        } catch (\Exception $e) {
            // Send failure email
            echo $e->getMessage();
            //Mail::to('admin@example.com')->send(new SyncStatusMail('Data synchronization failed: ' . $e->getMessage()));
        }

    }






    public function _getClass($className){
        $normalizedClassName = trim(strtolower($className));
        if ($normalizedClassName == 'government') {
            return PropertyClassification::where("class_name",  'Kogi State Govt.')->first();

        }elseif ($normalizedClassName == 'residential') {
            return PropertyClassification::where("class_name",  'Residential')->first();

        } elseif (in_array($normalizedClassName, ['recreational', 'religious', 'commercial'])) {
            return PropertyClassification::where("class_name",  'Commercial')->first();

        } elseif ($normalizedClassName == 'educational') {
            return PropertyClassification::where("class_name",  'Education (Private)')->first();

        } elseif ($normalizedClassName == 'health') {
            return PropertyClassification::where("class_name",  'Hospital')->first();

        } elseif ($normalizedClassName == 'open land') {
            return PropertyClassification::where("class_name",  'Vacant Properties & Open Land')->first();

        }  else {
            return null;
        }
    }

    public function _getChargeRate($occupier){
        $normalizedClassName = trim($occupier);
        if ($normalizedClassName == 'Owner_3rd_Party') {
            return ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (Owner and 3rd Party)%'])->first();

        }elseif ($normalizedClassName == 'Third_party') {
            return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (without Owner in residence)%'])->first();

        } elseif ($normalizedClassName == 'Owner_occupier') {
            return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

        } elseif ($normalizedClassName == 'Not_Known') {
            return ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

        }
    }


    private function _getZoneCharacter($char):string{
        switch (substr($char,0,1)){
            case 'A':
                return 'A';
            case 'B':
                return 'B';
            case 'C':
                return 'C';
            case 'D':
                return 'D';
            case 'E':
                return 'E';
            case 'F':
                return 'F';
            case 'G':
                return 'G';
            case 'H':
                return 'H';
            default:
                return 'Z';

        }
    }

    private function _getPavCode($classId, $zone, $syncWord){
        return PropertyAssessmentValue::where("class_id",$classId)
            ->where("zones",'LIKE', '%'.$zone.'%')
            ->where('sync_word', $syncWord)
            ->first();
    }






    private function dump(){
        if(!empty($pavRecord)){
            PropertyList::create([
                'address'=>$record->street_nam,
                'area'=>str_replace("_sqm", "", $areaVal),
                //'area'=>$record["Bld_area,
                'borehole'=>$record->water == 'Yes' ? 1 : 0,
                'building_code'=>$record->prop_id,
                'image'=>$record->photo_link,
                'owner_email'=>$record->owner_emai,
                'owner_gsm'=>$record->owner_phon,
                'owner_kgtin'=>$record->kgtin,
                'owner_name'=>$record->prop_owner,
                'title'=>$record->land_status,
                'pav_code'=> $pavRecord->pav_code ?? null,
                'power'=>$record->power == 'Yes' ? 1 : 0,
                //'refuse'=>$record["Street"],
                //'size'=>$record["Street"],
                'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                //'title'=>$record["Street"],
                'water'=>$record->water == 'Yes' ? 1 : 0,
                'zone_name'=>$zoneChar ?? 'A',
                'sub_zone'=>$record->zone ?? 'A1', //'A1','B2'
                'occupant'=>$record->prop_owner,
                'building_age'=>$record->property_age,
                'pay_status'=>null,//$record["Pay_Status"],
                'lga_id'=>!empty($lgaOne) && isset($lgaOne->id) ? $lgaOne->id : 1,//$lgaOne->id ?? 1,
                //'special'=>rand(0,1),
                'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                'class_id'=>$classID,//$record->landuse ?? 1,
                //'class_id'=>$propertyClassification->id ?? null,
                'sync_word'=>$syncWord,
                'property_use'=>$propertyUse->property_use ?? null,
                'cr'=>$chargeRate->id ?? 1,
                'actual_age'=>$record->property_age,
                'longitude'=>$record->longitude,
                'latitude'=>$record->latitude,
                'property_name'=>$record->prop_name,
                'occupier'=>$record->occupier_s,
                'property_address'=>$record->prop_addre,
                'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
            ]);
            //$counter++;
        }else{
            PropertyException::create([
                'address'=>$record->street_nam,
                'area'=>str_replace("_sqm", "", $areaVal),
                //'area'=>$record["Bld_area,
                'borehole'=>$record->water == 'Yes' ? 1 : 0,
                'building_code'=>$record->prop_id,
                'image'=>$record->photo_link,
                'owner_email'=>$record->owner_emai,
                'owner_gsm'=>$record->owner_phon,
                'owner_kgtin'=>$record->kgtin,
                'owner_name'=>$record->prop_owner,
                'title'=>$record->land_status,
                'pav_code'=>  null,
                'power'=>$record->power == 'Yes' ? 1 : 0,
                //'refuse'=>$record["Street"],
                //'size'=>$record["Street"],
                'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                //'title'=>$record["Street"],
                'water'=>$record->water == 'Yes' ? 1 : 0,
                'zone_name'=>$zoneChar ?? 'A',
                'sub_zone'=>$record->zone ?? 'A1', //'A1','B2'
                'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                'occupant'=>$record->prop_owner,
                'building_age'=>$record->property_age,
                'pay_status'=>null,//$record["Pay_Status"],
                'lga_id'=>!empty($lgaExist) && isset($lgaExist->id) ? $lgaExist->id : 1,
                //'special'=>rand(0,1),
                'class_id'=> $classID, //!empty($record->landuse) ? $record->landuse : 1,
                //'class_id'=>$propertyClassification->id ?? null,
                'cr'=>$chargeRate->id ?? 1,
                //'occupier_s'=>$record['occupier_s ?? '',
                'actual_age'=>$record->property_age,
                'longitude'=>$record->longitude,
                'latitude'=>$record->latitude,
                'property_name'=>$record->prop_name,
                'occupier'=>$record->occupier_s,
                'property_address'=>$record->prop_addre,
                'sync_word'=>$syncWord,
                'property_use'=>$propertyUse->property_use ?? null,
                'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
            ]);
        }
    }
}






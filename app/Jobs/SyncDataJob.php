<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyException;
use App\Models\PropertyList;
use App\Models\SynchronizationLog;
use App\Models\User;
use App\Models\Zone;
use App\Traits\UtilityTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Event\Code\Throwable;

class SyncDataJob implements ShouldQueue
{
    use Queueable;
    use UtilityTrait;
    public $lgaId;
    public $userId;
    public $tries = 10;
    public $timeout = 3600;
    public $synCounter;
    public $exceptionCounter;
    public $counter = 0;

    /**
     * Create a new job instance.
     */
    public function __construct($lga, $userId)
    {
        $this->lgaId = $lga;
        $this->userId = $userId;
        $this->exceptionCounter = 0;
        $this->synCounter = 0;
        $this->counter = 0;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //$this->lgaId = 0;


        try {
            $lgaName = '';
            if($this->lgaId > 0){
                $rec = Lga::find($this->lgaId);
                $lgaName = $rec->lga_name;
            }

            DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                ->when($this->lgaId > 0, function($query) use ($lgaName) {
                    return $query->where('lga', 'LIKE', "%{$lgaName}%");
                    //return $query->where('lga_id', $this->lgaId);
                })
                ->where('completeness_status', 'Complete')
                ->where('bill_sync', 0)
                //->take(1000)
                ->orderBy('id', 'ASC')
                ->cursor()
                ->each(function($record)  {
                    $this->counter++;
                    $lgaName = trim($record->lga);
                    $lgaOne = Lga::where('lga_name', 'LIKE', "%{$lgaName}%")->first();
                    $propertyList = PropertyList::where("building_code", $record->prop_id)->first();
                    $zoneChar = $this->_getZoneCharacter($record->zone) ?? 'Z';

                    $chargeRate = $this->_getChargeRate($record->occupier_s, $record->landuse);
                    $dep = Depreciation::where('range', $record->property_age)->first();
                    $areaVal = $this->convertToSqm($record->property_area);

                    //new property
                    $propertyClassification = PropertyClassification::find($record->landuse);
                    $zoneOne = Zone::where("sub_zone", $record->zone)->first();
                    $lgaIds = Lga::pluck('id')->toArray();
                    $lgaID = in_array($lgaOne->id, $lgaIds) ? $lgaOne->id : 1;
                    $classIds = PropertyClassification::pluck('id')->toArray();
                    //$classID = in_array($record->landuse, $classIds) ? $record->landuse : 1;
                    $lgaExist = Lga::find($lgaOne->id);
                    $reason = '';
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

                    $pavRecord = $this->_getPavCode($record->landuse, $record->zone, $syncWord);
                    $propertyUse = PropertyAssessmentValue::where('sync_word', $syncWord)->first();

                    if(empty($propertyList)){
                        $lgaReason = empty($lgaOne) ? 'LGA missing' : $lgaName;
                        $zoneReason = empty($zoneOne) ? 'Zone missing' : $record->zone;
                        $classReason = empty($propertyClassification) ? 'Prop. Class missing' : ($propertyClassification->class_name ?? 'Unknown');
                        $propUseReason = empty($propertyUse) ? 'Prop. Use missing' : ($propertyUse->sync_word ?? 'Unknown');

                        $reason = "Missing Info: ".$lgaReason." ".$zoneReason." ".$classReason." ".$propUseReason;
                        if(!empty($pavRecord)){
                            $exist = PropertyException::where('building_code', $record->prop_id)->first();
                            if(!empty($exist)){
                                $exist->status = 1; //synchronized
                                $exist->save();
                            }
                            PropertyList::create([
                                'address' => $record->street_nam,
                                'area' => !empty($areaVal) ? str_replace("_sqm", "", $areaVal) : 0,
                                'borehole' => $record->water == 'Yes' ? 1 : 0,
                                'building_code' => $record->prop_id,
                                'image' => $record->photo_link,
                                'owner_email' => $record->owner_emai,
                                'owner_gsm' => $record->owner_phon,
                                'owner_kgtin' => $record->kgtin,
                                'owner_name' => $record->prop_owner,
                                'title' => $record->land_status,
                                'pav_code' => $pavRecord->pav_code ?? null,
                                'power' => $record->power == 'Yes' ? 1 : 0,
                                'storey' => '',
                                'water' => $record->water == 'Yes' ? 1 : 0,
                                'zone_name' => $zoneChar ?? 'A',
                                'sub_zone' => $record->zone ?? 'A1',
                                'ward' => $record->ward ?? 'A1',
                                'occupant' => $record->prop_owner,
                                'building_age' => $record->property_age,
                                'pay_status' => null,
                                'lga_id' => !empty($lgaOne) && isset($lgaOne->id) ? $lgaOne->id : 1,
                                //'special'=>rand(0,1),
                                'class_name' => $propertyClassification->class_name ?? '',
                                'class_id' => in_array($record->landuse, $classIds) ? $record->landuse : 1,
                                'sync_word' => $syncWord,
                                'property_use' => $propertyUse->property_use ?? null,
                                'cr' => $chargeRate->id ?? 1,
                                'actual_age' => $record->property_age,
                                'longitude' => $record->longitude,
                                'latitude' => $record->latitude,
                                'property_name' => $record->prop_name,
                                'occupier' => $record->occupier_s,
                                'property_address' => $record->prop_addre,
                                'dep_id' => !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                            ]);
                            DB::connection('pgsql')
                                ->table('Land_Admin_New_Form')
                                ->where('id', $record->id)
                                ->update(['bill_sync' => 1]);
                            $this->synCounter++;

                        }
                        else{
                            $exist = PropertyException::where('building_code', $record->prop_id)->first();
                            if(empty($exist)){
                                PropertyException::create([
                                    'address'=>$record->street_nam ?? '',
                                    'area'=>!empty($areaVal) ? str_replace("_sqm", "", $areaVal) : 0,
                                    'borehole'=>$record->water == 'Yes' ? 1 : 0,
                                    'building_code'=>$record->prop_id ?? '',
                                    'image'=>$record->photo_link ?? '',
                                    'owner_email'=>$record->owner_emai ?? '',
                                    'owner_gsm'=>$record->owner_phon ?? '',
                                    'owner_kgtin'=>$record->kgtin ?? '',
                                    'owner_name'=>$record->prop_owner ?? '',
                                    'title'=>$record->land_status ?? '',
                                    'pav_code'=>  null,
                                    'power'=>$record->power == 'Yes' ? 1 : 0,
                                    'storey'=> '',
                                    'water'=>$record->water == 'Yes' ? 1 : 0,
                                    'zone_name'=>$zoneChar ?? 'A',
                                    'sub_zone'=>$record->zone ?? 'A1',
                                    'class_name'=> $propertyClassification->class_name ?? '' ,
                                    'occupant'=>$record->prop_owner ?? '',
                                    'building_age'=>$record->property_age ?? '',
                                    'pay_status'=>null,
                                    'lga_id'=>!empty($lgaExist) && isset($lgaExist->id) ? $lgaExist->id : 1,
                                    'class_id'=> in_array($record->landuse, $classIds) ? $record->landuse : 1,
                                    'cr'=>$chargeRate->id ?? 1,
                                    'actual_age'=>$record->property_age ?? '',
                                    'longitude'=>$record->longitude ?? '',
                                    'latitude'=>$record->latitude ?? '',
                                    'property_name'=>$record->prop_name ?? '',
                                    'occupier'=>$record->occupier_s ?? '',
                                    'property_address'=>$record->prop_addre ?? '',
                                    'sync_word'=>$syncWord ?? '',
                                    'property_use'=>$propertyUse->property_use ?? null,
                                    'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                                    'reason'=>$reason,
                                ]);
                                $this->exceptionCounter++;
                            }
                        }
                        //}

                    }else{
                        DB::connection('pgsql')
                            ->table('Land_Admin_New_Form')
                            ->where('id', $record->id)
                            ->update(['bill_sync' => 1]);
                    }
                });

            //log activity
            $this->_logActivity();


        } catch (\Exception $e) {
            echo "Line:: ".$e->getLine(). "Message:: ".$e->getMessage();
            $this->_logActivity();

        }


    }

    private function _logActivity(){
        //log activity
        $user = User::find($this->userId);
        if(!empty($user)){
            $total = $this->exceptionCounter + $this->synCounter;
            $title = "Property synchronization";
            $narration = "{$user->name} synchronized about  {$this->synCounter} properties. About {$this->exceptionCounter} went into exception. A total of
                {$this->counter} properties were synchronized.";
            ActivityLog::LogActivity($title, $narration , $user->id);
            SynchronizationLog::logSyncReport($this->counter, $this->synCounter, now(), $this->lgaId);
        }
    }


    public function failed(Throwable $e)
    {
        $this->_logActivity();
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

    public function _getChargeRate($occupier, $landUse){
        $normalizedClassName = trim($occupier);
        switch($landUse){
            case 1:
                if ($normalizedClassName == 'Owner_3rd_Party') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (Owner and 3rd Party)%'])->first();
                }elseif ($normalizedClassName == 'Third_party') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (without Owner in residence)%'])->first();

                } elseif ($normalizedClassName == 'Owner_occupier') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                } elseif ($normalizedClassName == 'Not_Known') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                }
                break;
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
}








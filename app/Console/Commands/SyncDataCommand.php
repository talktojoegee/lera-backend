<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Event\Code\Throwable;

class SyncDataCommand extends Command
{ use UtilityTrait;

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
    /*    public function __construct($lga, $userId)
        {
            $this->lgaId = $lga;
            $this->userId = $userId;
            $this->exceptionCounter = 0;
            $this->synCounter = 0;
            $this->counter = 0;

        }*/

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
    public function handle(): void
    {
        //$this->lgaId = 0;


        try {
            $lgaName = '';
            /*   if($this->lgaId > 0){
                   $rec = Lga::find($this->lgaId);
                   $lgaName = $rec->lga_name;
               }*/
            $lgaId = 12;

            DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                /*->when($lgaId > 0, function($query) use ($lgaName) {
                    return $query->where('lga', 'LIKE', "%{$lgaName}%");

                })*/
                ->where('completeness_status', 'Complete')
                ->where('bill_sync', 0)
                ->whereIn('prop_id', [
                    'Kg/Ere/825958',
                    'Kg/KAB/794760',
                    'Kg/DAH/876718',
                ])
                //->take(20)
                ->orderBy('id', 'DESC')
                ->cursor()
                ->each(function($record)  {
                    //print_r($record);
                    $this->counter++;
                    $lgaName = trim($record->lga);
                    $normalizedName = str_replace(['_', '/'], '', strtolower($lgaName));
                    $lgaOne = Lga::get()->first(function($lga) use ($normalizedName) {
                        return str_replace(['_', '/'], '', strtolower($lga->lga_name)) === $normalizedName;
                    });
                    $propertyList = PropertyList::where("building_code", $record->prop_id)->first();
                    $zoneChar = $this->_getZoneCharacter($record->zone) ?? 'Z';
                    echo "Property ID:: ".$record->prop_id." \n";

                    $chargeRate = $this->_getChargeRate($record->landuse);
                    //$chargeRate = $this->_getChargeRate($record->occupier_s, $record->landuse);
                    $dep = Depreciation::where('range', $record->property_age)->first();
                    $areaVal = $this->convertToSqm($record->property_area);

                    //new property
                    $propertyClassification = PropertyClassification::find($record->landuse);
                    $zoneOne = Zone::where("sub_zone", $record->zone)->first();
                    //$lgaIds = Lga::pluck('id')->toArray();
                    //$lgaID = in_array($lgaOne->id, $lgaIds) ? $lgaOne->id : 1;
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


                    //print_r($record);
                    echo "Motives";
                    $pavRecord = $this->_getPavCode($record->landuse, $record->zone, $syncWord);
                    $propertyUse = PropertyAssessmentValue::where('sync_word', $syncWord)->first();
                    $id = $lgaOne->id;
                    $name = $lgaOne->lga_name;
                    $code = $pavRecord->pav_code ?? 'N/A';
                    echo "Sync Word:: ".$syncWord." LGA ID:: {$id} LGA Name:: {$name} Billing Code:: {$code} Building Code:: {$record->prop_id} \n";

                    if(!$propertyList){
                        echo "Empty property";
                        $ba = $record->area_from_bfp;
                        $lgaReason = empty($lgaOne) ? 'LGA missing:: ' : $lgaName." ;";
                        $zoneReason = empty($zoneOne) ? 'Zone missing:: ' : $record->zone." ;";
                        $classReason = empty($propertyClassification) ? 'Prop. Class missing:: ' : ($propertyClassification->class_name ?? 'Unknown')." ;";
                        $propUseReason = empty($propertyUse) ? 'Prop. Use missing:: ' : ($propertyUse->sync_word ?? 'Unknown')." ;";
                        $baReason = !isset($ba) ? "BA missing:: " : $ba." ;";


                        if(!empty($pavRecord) && isset($ba)){
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
                                'ba' => $record->area_from_bfp ?? 200
                            ]);
                            DB::connection('pgsql')
                                ->table('Land_Admin_New_Form')
                                ->where('id', $record->id)
                                ->update(['bill_sync' => 1]);
                            $this->synCounter++;

                        }
                        else{
                            $reason = $lgaReason." ".$zoneReason." ".$classReason." ".$propUseReason." ".$baReason;
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
            //$this->_logActivity();


        } catch (\Exception $e) {
            //echo "Line:: ".$e->getLine(). "Message:: ".$e->getMessage();
            // $this->_logActivity();

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

    public function _getChargeRate($landUse){
        //$normalizedClassName = trim($occupier);
        switch($landUse){
            case 1:
                return ChargeRate::first();
                /*if ($normalizedClassName == 'Owner_3rd_Party') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (Owner and 3rd Party)%'])->first();
                }elseif ($normalizedClassName == 'Third_party') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (without Owner in residence)%'])->first();

                } elseif ($normalizedClassName == 'Owner_occupier') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                } elseif ($normalizedClassName == 'Not_Known') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                }*/
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


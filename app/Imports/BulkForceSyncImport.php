<?php
namespace App\Imports;

use App\Models\ActivityLog;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use App\Models\User;
use App\Models\Zone;
use App\Traits\UtilityTrait;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

class BulkForceSyncImport implements ToModel, WithStartRow, WithMultipleSheets
{
    use UtilityTrait;
    public $userId;

    public function __construct($userId){
        $this->userId = $userId;
    }

    public function model(array $row)
    {
        if(!empty($row)){
            $this->forceSynchronizeProperty($row[0]);
        }
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function startRow(): int
    {
        return 2; // Start from the second row (assuming first row is a header)
    }



    public function forceSynchronizeProperty($buildingCode){


        $propertyDetail = PropertyList::where('building_code', $buildingCode)->first();
        if(!empty($propertyDetail)){
            $property = DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                ->where('prop_id', $buildingCode)
                ->first();
            if(!empty($property)){

                //return response()->json(['data'=>$property],200);
                $lgaName = trim($property->lga);
                $lgaOne = Lga::where('lga_name', 'LIKE', "%{$lgaName}%")->first();
                $zoneChar = $this->_getZoneCharacter($property->zone) ?? 'Z';

                $propertyClassification = PropertyClassification::find($property->landuse);
                $zoneOne = Zone::where("sub_zone", $property->zone)->first();
                $lgaIds = Lga::pluck('id')->toArray();
                $lgaID = in_array($lgaOne->id, $lgaIds) ? $lgaOne->id : 1;
                $classIds = PropertyClassification::pluck('id')->toArray();
                $lgaExist = Lga::find($lgaOne->id);

                $chargeRate = $this->_getChargeRate($property->occupier_s, $property->landuse);
                $dep = Depreciation::where('range', $property->property_age)->first();
                $areaVal = $this->convertToSqm($property->property_area);
                $syncWord = null;

                if(!is_null($property->residentia)){
                    $syncWord = $property->residentia;
                }else if(!is_null($property->commercial)){
                    $syncWord = $property->commercial;
                }else if(!is_null($property->industrial)){
                    $syncWord = $property->industrial;
                }else if(!is_null($property->industri_1)){
                    $syncWord = $property->industri_1;
                }else if(!is_null($property->education)){
                    $syncWord = $property->education;
                }else if(!is_null($property->agricultur)){
                    $syncWord = $property->agricultur;
                }else if(!is_null($property->transport)){
                    $syncWord = $property->transport;
                }else if(!is_null($property->utility)){
                    $syncWord = $property->utility;
                }else if(!is_null($property->kgsg_publi)){
                    $syncWord = $property->kgsg_publi;
                }else if(!is_null($property->fgn_public)){
                    $syncWord = $property->fgn_public;
                }else if(!is_null($property->religious)){
                    $syncWord = $property->religious;
                }else if(!is_null($property->others)){
                    $syncWord = $property->others;
                }
                $pavRecord = $this->_getPavCode($property->landuse, $property->zone, $syncWord);
                $propertyUse = PropertyAssessmentValue::where('sync_word', $syncWord)->first();
                if(!empty($pavRecord)){
                    PropertyList::where('id', $propertyDetail->id)
                        ->update([
                            'address' => $property->street_nam ?? '',
                            'area' => !empty($areaVal) ? str_replace("_sqm", "", $areaVal) : 0,
                            'borehole' => $property->water == 'Yes' ? 1 : 0,
                            'image' => $property->photo_link,
                            'owner_email' => $property->owner_emai,
                            'owner_gsm' => $property->owner_phon,
                            'owner_kgtin' => $property->kgtin,
                            'owner_name' => $property->prop_owner,
                            'title' => $property->land_status,
                            'pav_code' => $pavRecord->pav_code ?? null,
                            'power' => $property->power == 'Yes' ? 1 : 0,
                            'storey' => '',
                            'water' => $property->water == 'Yes' ? 1 : 0,
                            'zone_name' => $zoneChar ?? 'A',
                            'sub_zone' => $property->zone ?? 'A1',
                            'ward' => $property->ward ?? 'A1',
                            'occupant' => $property->prop_owner,
                            'building_age' => $property->property_age,
                            'pay_status' => null,
                            'lga_id' => !empty($lgaOne) && isset($lgaOne->id) ? $lgaOne->id : 1,
                            'class_name' => $propertyClassification->class_name ?? '',
                            'class_id' => in_array($property->landuse, $classIds) ? $property->landuse : 1,
                            'sync_word' => $syncWord,
                            'property_use' => $propertyUse->property_use ?? null,
                            'cr' => $chargeRate->id ?? 1,
                            'actual_age' => $property->property_age,
                            'longitude' => $property->longitude,
                            'latitude' => $property->latitude,
                            'property_name' => $property->prop_name,
                            'occupier' => $property->occupier_s,
                            'property_address' => $property->prop_addre,
                            'dep_id' => !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id,
                        ]);
                    //log activity
                    $user = User::find($this->userId);
                    if(!empty($user)){
                        $title = "Bulk Property Force Synchronization";
                        $narration = "{$user->name} forcefully synchronized a property with the building code: {$propertyDetail->building_code}";
                        ActivityLog::LogActivity($title, $narration , $user->id);
                    }

                }
            }

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



/*'Kg/AJA/963898',
'Kg/AJA/955410',
'Kg/GMN/886803',
'Kg/GMN/765274',
'Kg/GMN/757024',
'Kg/GMN/759151',
'Kg/GMN/756974',
'Kg/GMN/875211',
'Kg/AJA/690060',
'Kg/LKJ/715573',
'Kg/LKJ/865115',
'Kg/LKJ/254340',*/

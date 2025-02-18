<?php

namespace App\Http\Controllers;

use App\Http\Resources\SyncResource;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyException;
use App\Models\PropertyList;
use App\Models\SynchronizationLog;
use App\Models\Zone;
use App\Traits\UtilityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoteController extends Controller
{
    use UtilityTrait;

    public function __construct(){

    }


    public function showBuildingsByLGAId($lgaId){
        $lga = Lga::find($lgaId);
        if(empty($lga) && $lgaId != 0){
            return response()->json(['data'=>"Whoops! Something went wrong."],401);
        }
        $addedCount = 0;
        $rejectedCount = 0;
        //$data = [];
        if($lgaId == 0){
            $data = $this->_fetchAllBuildings();
        }else{
            $data =  $this->_fetchBuildingsByLGAName($lga->lga_name);
        }
        if(count($data['data']) <= 0){
            return response()->json(['data'=>"Whoops! Nothing to synchronize"],401);
        }
        $test = null;
        foreach($data['data'] as  $record){
             $lgaOne = Lga::where('lga_name',$record['lga'])->first();
             //$lgaOne = Lga::where('lga_name',$record['LGA'])->first();
             $propertyList = PropertyList::where("building_code", $record["prop_id"])->first();
             //$propertyList = PropertyList::where("building_code", $record["Bld_ID"])->first();
            //$propertyClassification = $this->_getClass($record["Bld_Cat"]);
            $propertyClassification = PropertyClassification::find($record['landuse']);// $this->_getClass($record["Bld_Cat"]);
            $zoneOne = Zone::where("sub_zone", $record["zone"])->first();
            //$zoneOne = Zone::where("sub_zone", $record["Zone"])->first();

              if (!empty($lgaOne) /*&& !empty($propertyClassification)*/ && !empty($zoneOne)) {
                  //$medianAge = $this->median(); [in the property table, save media age, actual age,]
                  //add charge rate ID to the property table
                  //2. Charge rate will be based on occupancy
                  //carry occupancy details from property table(from GIS) and match it with occupancy on charge rate table
                  //then it will assign a charge rate ID to the property


                  //When Processing Bill
                  //if you don't have billing code, median age or charge rate ID - bill cannot be calculated
                  //
                  if (empty($propertyList)) {
                      $syncWord = null;

                      if(!is_null($record["residentia"])){
                          $syncWord = $record["residentia"];
                      }else if(!is_null($record["commercial"])){
                          $syncWord = $record["commercial"];
                      }else if(!is_null($record["industrial"])){
                          $syncWord = $record["industrial"];
                      }else if(!is_null($record["industri_1"])){
                          $syncWord = $record["industri_1"];
                      }else if(!is_null($record["education"])){
                          $syncWord = $record["education"];
                      }else if(!is_null($record["agricultur"])){
                          $syncWord = $record["agricultur"];
                      }else if(!is_null($record["transport"])){
                          $syncWord = $record["transport"];
                      }else if(!is_null($record["utility"])){
                          $syncWord = $record["utility"];
                      }else if(!is_null($record["kgsg_publi"])){
                          $syncWord = $record["kgsg_publi"];
                      }else if(!is_null($record["fgn_public"])){
                          $syncWord = $record["fgn_public"];
                      }else if(!is_null($record["religious"])){
                          $syncWord = $record["religious"];
                      }else if(!is_null($record["others"])){
                          $syncWord = $record["others"];
                      }

                     $zoneChar = $this->_getZoneCharacter($record['zone']) ?? 'Z';
                     $chargeRate = $this->_getChargeRate($record['occupier_s']);
                     $dep = Depreciation::where('range', $record['property_age'])->first();
                    //pav
                      //When synchronizing properties, First Match Zones, Class & Occupancy – this you have done already
                      $pavRecord = $this->_getPavCode($record['landuse'], $record["zone"], $syncWord);
                      //$pavRecord = $this->_getPavCode($propertyClassification->id, $record["Occupant"], $record["Zone"]);
                      //if(!empty($pavRecord)){
                      $areaVal = $this->convertToSqm($record["property_area"]);



                          $propertyUse = PropertyAssessmentValue::where('sync_word', $syncWord)->first();

                if(!empty($pavRecord)){
                    PropertyList::create([
                        'address'=>$record["street_nam"],
                        'area'=>str_replace("_sqm", "", $areaVal),
                        //'area'=>$record["Bld_area"],
                        'borehole'=>$record["water"] == 'Yes' ? 1 : 0,
                        'building_code'=>$record["prop_id"],
                        'image'=>$record["photo_link"],
                        'owner_email'=>$record["owner_emai"],
                        'owner_gsm'=>$record["owner_phon"],
                        'owner_kgtin'=>$record["kgtin"],
                        'owner_name'=>$record["prop_owner"],
                        'title'=>$record["land_status"],
                        'pav_code'=> $pavRecord->pav_code ?? null,
                        'power'=>$record["power"] == 'Yes' ? 1 : 0,
                        //'refuse'=>$record["Street"],
                        //'size'=>$record["Street"],
                        'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                        //'title'=>$record["Street"],
                        'water'=>$record["water"] == 'Yes' ? 1 : 0,
                        'zone_name'=>$zoneChar,
                        'sub_zone'=>$record["zone"], //'A1','B2'
                        'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                        'occupant'=>$record["prop_owner"],
                        'building_age'=>$record["property_age"],
                        'pay_status'=>null,//$record["Pay_Status"],
                        'lga_id'=>$lgaOne->id ?? null,
                        //'special'=>rand(0,1),
                        'class_id'=>$record['landuse'],
                        'sync_word'=>$syncWord,
                        'property_use'=>$propertyUse->property_use ?? null,
                        //'class_id'=>$propertyClassification->id ?? null,
                        'cr'=>$chargeRate->id ?? 1,
                        'actual_age'=>$record['property_age'],
                        'longitude'=>$record['longitude'],
                        'latitude'=>$record['latitude'],
                        'property_name'=>$record['prop_name'],
                        'occupier'=>$record['occupier_s'],
                        'property_address'=>$record['prop_addre'],
                        'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                    ]);
                    $addedCount++;
                }else{
                    PropertyException::create([
                        'address'=>$record["street_nam"],
                        'area'=>str_replace("_sqm", "", $areaVal),
                        //'area'=>$record["Bld_area"],
                        'borehole'=>$record["water"] == 'Yes' ? 1 : 0,
                        'building_code'=>$record["prop_id"],
                        'image'=>$record["photo_link"],
                        'owner_email'=>$record["owner_emai"],
                        'owner_gsm'=>$record["owner_phon"],
                        'owner_kgtin'=>$record["kgtin"],
                        'owner_name'=>$record["prop_owner"],
                        'title'=>$record["land_status"],
                        'pav_code'=>  null,
                        'power'=>$record["power"] == 'Yes' ? 1 : 0,
                        //'refuse'=>$record["Street"],
                        //'size'=>$record["Street"],
                        'storey'=> '',//is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                        //'title'=>$record["Street"],
                        'water'=>$record["water"] == 'Yes' ? 1 : 0,
                        'zone_name'=>$zoneChar,
                        'sub_zone'=>$record["zone"], //'A1','B2'
                        'class_name'=> $propertyClassification->class_name ?? '' ,//$record["Bld_Cat"],
                        'occupant'=>$record["prop_owner"],
                        'building_age'=>$record["property_age"],
                        'pay_status'=>null,//$record["Pay_Status"],
                        'lga_id'=>$lgaOne->id ?? null,
                        //'special'=>rand(0,1),
                        'class_id'=>$record['landuse'],
                        //'class_id'=>$propertyClassification->id ?? null,
                        'cr'=>$chargeRate->id ?? 1,
                        //'occupier_s'=>$record['occupier_s'] ?? '',
                        'actual_age'=>$record['property_age'],
                        'longitude'=>$record['longitude'],
                        'latitude'=>$record['latitude'],
                        'property_name'=>$record['prop_name'],
                        'occupier'=>$record['occupier_s'],
                        'property_address'=>$record['prop_addre'],
                        'sync_word'=>$syncWord,
                        'property_use'=>$propertyUse->property_use ?? null,
                        'dep_id'=> !empty($dep) ? $dep->id : Depreciation::orderBy('id', 'ASC')->first()->id, //depreciation
                    ]);
                    $rejectedCount++;
                }


                     // }

                  }/*else{
                      $zoneChar = $this->_getZoneCharacter($record['zone']) ?? 'Z';
                      $chargeRate = $this->_getChargeRate($record['occupier_s']);
                      $dep = Depreciation::where('range', $record['property_age'])->first();
                      $pavRecord = $this->_getPavCode($record['landuse'], $record["zone"], $syncWord);
                      $areaVal = $this->convertToSqm($record["property_area"]);

                  }*/
              }
        }
        //Log report
        SynchronizationLog::logSyncReport(($addedCount + $rejectedCount), $addedCount, now(), $lgaId);
        return response()->json(["data"=>"{$addedCount} records synchronized! {$rejectedCount} records skipped."], 200);
    }

    private function _fetchBuildingsByLGAName($lgaName)
    {
        //$url = env('REMOTE_LIVE_SERVER')."lga/{$lgaName}";
        $url = env('REMOTE_LOCAL_SERVER')."lga/{$lgaName}";

        $response = Http::withHeaders([
            //'Authorization' => 'Bearer your-access-token',
            'Accept' => 'application/json',
        ])->get($url);
        // Get the response body as JSON
        //$data = $response->json();

        $data = json_decode($response->getBody(), true);
        if ($response->successful()) {
            // Process the data
            return $data; //response()->json(['data' => $data], 200);
        } else {
            return response()->json(['data' => $response->body()], $response->status());
        }
    }
    private function _fetchAllBuildings()
    {
        $url = "http://127.0.0.1:8000/api/lga-list";
        //$url = "http://laravel.kofooni.ca/api/lga-list";

        $response = Http::withHeaders([
            //'Authorization' => 'Bearer your-access-token',
            'Accept' => 'application/json',
        ])->get($url);
        $data = json_decode($response->getBody(), true);
        if ($response->successful()) {
            return $data;
        } else {
            return response()->json(['data' => $response->body()], $response->status());
        }
    }


    public function showSyncReport(Request $request){
        return response()->json([
            'data'=>SyncResource::collection(SynchronizationLog::getSyncReport($request->limit, $request->skip)),
            'total'=>SynchronizationLog::count()
            ]);
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
/*
    private function _getPavCode($classId, $occupancy, $zone){
        $pavRecord = null;
        switch (strtolower($occupancy)){
            case 'tenant':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("property_use", '3rd Party Only')
                    //->orWhere("occupancy", 'Commercial')
                    ->orWhere("property_use", 'Used for Business') //Religious
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();
            case 'owner':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("property_use", 'Owner Occupied') //owner
                    ->orWhere("property_use", 'Kogi State Govt') //Government
                    ->orWhere("property_use", 'Used for Business') //Recreational
                    ->orWhere("property_use", 'Vacant/Open Empty Land') //Open Land
                    ->orWhere("property_use", 'Used for Business') //Health || Educational
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();


        }
        return $pavRecord;
    }*/

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


}

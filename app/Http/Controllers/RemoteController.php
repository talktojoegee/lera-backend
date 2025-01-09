<?php

namespace App\Http\Controllers;

use App\Http\Resources\SyncResource;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use App\Models\SynchronizationLog;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RemoteController extends Controller
{
    public function __construct(){

    }


    public function showBuildingsByLGAId($lgaId){
        $lga = Lga::find($lgaId);
        if(empty($lga)){
            return response()->json(['data'=>"Whoops! Something went wrong."],401);
        }
        $addedCount = 0;
        $rejectedCount = 0;
        //load properties by local government
        $data =  $this->_fetchBuildingsByLGAName($lga->lga_name);
        //return $data;
        foreach($data['data'] as  $record){
            //return response()->json(['some'=>$record['LGA']  ],200) ;

             $lgaOne = Lga::where('lga_name',$record['LGA'])->first();;
             $propertyList = PropertyList::where("building_code", $record["Bld_ID"])->first();
             $propertyClassification = $this->_getClass($record["Bld_Cat"]); //PropertyClassification::where("class_name", $record["Bld_Cat"])->first();
             $zoneOne = Zone::where("sub_zone", $record["Zone"])->first();

              if (!empty($lgaOne) && !empty($propertyClassification) && !empty($zoneOne)) {
                  if (empty($propertyList)) {
                     $zoneChar = $this->_getZoneCharacter($record['Zone']) ?? 'Z';
                    //pav
                      $pavRecord = $this->_getPavCode($propertyClassification->id, $record["Occupant"], $record["Zone"]);
                      if(!empty($pavRecord)){
                          PropertyList::create([
                              'address'=>$record["Street"],
                              'area'=>$record["Bld_area"],
                              'borehole'=>$record["Borehole"] == 'Yes' ? 1 : 0,
                              'building_code'=>$record["Bld_ID"],
                              'image'=>$record["Photo"],
                              //'owner_email'=>$record["Street"],
                              //'owner_gsm'=>$record["Street"],
                              'owner_kgtin'=>$record["KGTIN"],
                              'owner_name'=>$record["Owner"],
                              'pav_code'=> $pavRecord->pav_code ?? null,
                              'power'=>$record["Power"] == 'Yes' ? 1 : 0,
                              //'refuse'=>$record["Street"],
                              //'size'=>$record["Street"],
                              'storey'=> is_int($record["Bld_Storey"]) ? $record["Bld_Storey"] : 0,
                              //'title'=>$record["Street"],
                              'water'=>$record["Water"] == 'Yes' ? 1 : 0,
                              'zone_name'=>$zoneChar,
                              'sub_zone'=>$record["Zone"], //'A1','B2'
                              'class_name'=>$record["Bld_Cat"],
                              'occupant'=>$record["Occupant"],
                              'building_age'=>$record["Bld_Age"],
                              'pay_status'=>$record["Pay_Status"],
                              'lga_id'=>$lgaId ?? null,
                              'class_id'=>$propertyClassification->id ?? null,
                          ]);
                          $addedCount++;
                      }

                  }else{
                      $rejectedCount++;
                  }
              }
        }
        //Log report
        SynchronizationLog::logSyncReport(($addedCount + $rejectedCount), $addedCount, now(), $lgaId);
        return response()->json(["data"=>"{$addedCount} records synchronized! {$rejectedCount} records skipped."], 200);
    }

    private function _fetchBuildingsByLGAName($lgaName)
    {
        $url = "http://laravel.kofooni.ca/api/lga/{$lgaName}";
        //$url = "http://127.0.0.1:8000/api/lga/{$lgaName}";

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


    public function showSyncReport(){
        return SyncResource::collection(SynchronizationLog::getSyncReport());
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

    private function _getPavCode($classId, $occupancy, $zone){
        $pavRecord = null;
        switch (strtolower($occupancy)){
            case 'tenant':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("occupancy", '3rd Party Only')
                    //->where("occupancy", $occupancy)
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();
            case 'owner':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("occupancy", 'Owner Occupied')
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();


        }
        return $pavRecord;
    }

    private function _getClass($className){
        $normalizedClassName = $className; //trim(strtolower($className));
        if ($normalizedClassName == 'Government') {
            return PropertyClassification::where("class_name",  'Kogi State Govt.')->first();

        }elseif ($normalizedClassName == 'Residential') {
            return PropertyClassification::where("class_name",  'Residential')->first();

        } elseif (in_array($normalizedClassName, ['Recreational', 'Religious', 'Commercial'])) {
            return PropertyClassification::where("class_name",  'Commercial')->first();

        } elseif ($normalizedClassName == 'Educational') {
            return PropertyClassification::where("class_name",  'Education (Private)')->first();

        } elseif ($normalizedClassName == 'Health') {
            return PropertyClassification::where("class_name",  'Hospital')->first();

        } elseif ($normalizedClassName == 'Open Land') {
            return PropertyClassification::where("class_name",  'Vacant Properties & Open Land')->first();

        }  else {
            return null;
        }
        /*switch ($normalizedClassName){
            case 'government':
                return  PropertyClassification::where("class_name", 'LIKE', 'Kogi State Govt.')->first();
                //PropertyClassification::where("class_name", $record["Bld_Cat"])->first();

            case 'recreational':
            case 'religious':
            case 'commercial':
                return  PropertyClassification::where("class_name", 'LIKE', 'Commercial')->first();
            case 'educational':
                return  PropertyClassification::where("class_name", 'LIKE', 'Education (Private)')->first();
            case 'health':
                return  PropertyClassification::where("class_name", 'LIKE', 'Hospital')->first();
            case 'open land':
                return  PropertyClassification::where("class_name", 'LIKE', 'Vacant Properties & Open Land')->first();
            case 'residential':
                return PropertyClassification::where("class_name", 'LIKE', 'Residential')->first();
            default:
                return null;

        }*/
    }
}

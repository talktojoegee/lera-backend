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
use Illuminate\Support\Facades\Log;

class RemoteController extends Controller
{
    public function __construct(){

    }


    public function showBuildingsByLGAId($lgaId){
        $lga = Lga::find($lgaId);
        if(empty($lga) && $lgaId != 0){
            return response()->json(['data'=>"Whoops! Something went wrong."],401);
        }
        $addedCount = 0;
        $rejectedCount = 0;
        if($lgaId == 0){
            $data = $this->_fetchAllBuildings();
        }else{
            $data =  $this->_fetchBuildingsByLGAName($lga->lga_name);
        }
        if(count($data['data']) <= 0){
            return response()->json(['data'=>"Whoops! Nothing to synchronize"],401);
        }
        foreach($data['data'] as  $record){
             $lgaOne = Lga::where('lga_name',$record['LGA'])->first();;
             $propertyList = PropertyList::where("building_code", $record["Bld_ID"])->first();
            $propertyClassification = $this->_getClass($record["Bld_Cat"]);
            $zoneOne = Zone::where("sub_zone", $record["Zone"])->first();

              if (!empty($lgaOne) /*&& !empty($propertyClassification)*/ && !empty($zoneOne)) {
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
                              'lga_id'=>$lgaOne->id ?? null,
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
        //$url = "http://laravel.kofooni.ca/api/lga/{$lgaName}";
        $url = "http://127.0.0.1:8000/api/lga/{$lgaName}";

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

    private function _getPavCode($classId, $occupancy, $zone){
        $pavRecord = null;
        switch (strtolower($occupancy)){
            case 'tenant':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("occupancy", '3rd Party Only')
                    //->orWhere("occupancy", 'Commercial')
                    ->orWhere("occupancy", 'Used for Business') //Religious
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();
            case 'owner':
                return PropertyAssessmentValue::where("class_id", $classId)
                    ->where("occupancy", 'Owner Occupied') //owner
                    ->orWhere("occupancy", 'Kogi State Govt') //Government
                    ->orWhere("occupancy", 'Used for Business') //Recreational
                    ->orWhere("occupancy", 'Vacant/Open Empty Land') //Open Land
                    ->orWhere("occupancy", 'Used for Business') //Health || Educational
                    ->where("zones",'LIKE', '%'.$zone.'%')
                    ->first();


        }
        return $pavRecord;
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
}

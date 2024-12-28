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
             $propertyClassification = PropertyClassification::where("class_name", $record["Bld_Cat"])->first();
             $zoneOne = Zone::where("sub_zone", $record["Zone"])->first();

              if (!empty($lgaOne) && !empty($propertyClassification) && !empty($zoneOne)) {
                  if (empty($propertyList)) {
                    //pav
                      $pavRecord = PropertyAssessmentValue::where("class_id", $propertyClassification->id)
                          //->where("occupancy", $record['Occupant'])
                          ->where("zones",'LIKE', '%'.$record['Zone'].'%')
                          ->first();


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
                          'zone_name'=>$record["Zone"],
                          'lga_id'=>$lgaId ?? null,
                          'class_id'=>$propertyClassification->id ?? null,
                      ]);
                      $addedCount++;
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
        $url = "http://127.0.0.1:8001/api/lga/{$lgaName}";
        $response = Http::withHeaders([
            //'Authorization' => 'Bearer your-access-token',
            'Accept' => 'application/json',
        ])->get($url);
        // Get the response body as JSON
        $data = $response->json();
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
}

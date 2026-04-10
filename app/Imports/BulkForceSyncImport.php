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
    public $buildingCodeList ;
    public $counter;

    public function __construct($userId){
        $this->userId = $userId;
        $this->buildingCodeList = [];
        $this->counter = 0;

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
                            'distribution_code'=>$property->enum_cell_number ?? null,
                            'tracker'=>1,
                        ]);
                    array_push($this->buildingCodeList, $property->prop_id);
                    //log activity


                }
            }

        }
        if(count($this->buildingCodeList) > 0){
            $this->_logActivity();
        }

    }

    private function _logActivity(){
        $user = User::find($this->userId);
        if(!empty($user)){
            $list = implode('","', $this->buildingCodeList);
            $title = "Bulk Property Force Synchronization";
            $narration = "{$user->name} forcefully synchronized the following list of properties:  {$list}";
            ActivityLog::LogActivity($title, $narration , $user->id);
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


/*
Kg/LKJ/975859
Kg/GMN/793503
Kg/AJA/1090367
Kg/LKJ/1092500
Kg/AJA/1092086
Kg/LKJ/091072
Kg/AJA/1092058
Kg/AJA/1092123
Kg/AJA/1091439
Kg/AJA/605486
Kg/AJA/306707
Kg/KKH/562135
Kg/KKH/153379
Kg/AJA/566386
Kg/AJA/162622
Kg/KKH/507824
Kg/AJA/281236
Kg/LKJ/241385
Kg/LKJ/146613
Kg/LKJ/824974
Kg/AJA/595229
Kg/LKJ/520666
Kg/AJA/075832
Kg/AJA/736262
Kg/KKH/338095
Kg/AJA/477635
Kg/AJA/530450
Kg/KKH/474172
Kg/KKH/449691
Kg/AJA/1090487
Kg/LKJ/760813
Kg/KKH/760701
Kg/KKH/701376
Kg/AJA/792293
Kg/LKJ/957028
Kg/AJA/1090472
Kg/AJA/814967
Kg/KKH/504670
Kg/KKH/169309
Kg/AJA/1090376
Kg/AJA/580977
Kg/KKH/777251
Kg/LKJ/773775
Kg/LKJ/961978
Kg/AJA/907224
Kg/AJA/134135
Kg/AJA/530061
Kg/AJA/795778
Kg/AJA/867982
Kg/AJA/250212
Kg/AJA/251470
Kg/AJA/223023
Kg/AJA/291314
Kg/KKH/027891
Kg/AJA/676259
Kg/GMN/990377
Kg/AJA/164539
Kg/LKJ/797123
Kg/KKH/010565
Kg/KKH/039020
Kg/LKJ/120963
Kg/AJA/1090379
Kg/LKJ/974141
Kg/LKJ/272984
Kg/KKH/965727
Kg/AJA/565743
Kg/AJA/892934
Kg/AJA/955648
Kg/AJA/581983
Kg/AJA/552310
Kg/AJA/147308
Kg/KKH/452181
Kg/LKJ/544516
Kg/KKH/360092
Kg/GMN/144296
Kg/LKJ/228797
Kg/AJA/1092114
Kg/KKH/666432
Kg/AJA/1092107
Kg/AJA/203366
Kg/AJA/649487
Kg/AJA/1092049
Kg/KKH/192987
Kg/KKH/777528
Kg/LKJ/600051
Kg/AJA/711620
Kg/GMN/034175
Kg/KKH/077474
Kg/GMN/702010
Kg/KKH/348402
Kg/AJA/884513
Kg/KKH/283766
Kg/AJA/918908
Kg/LKJ/027268
Kg/KKH/734364
Kg/AJA/411331
Kg/AJA/470336
Kg/LKJ/461896
Kg/AJA/398570
Kg/AJA/437542
Kg/AJA/1092121
Kg/AJA/1095866
Kg/LKJ/761362
Kg/LKJ/111046
Kg/KKH/680616
Kg/KKH/494846
Kg/AJA/1092148
Kg/AJA/058511
Kg/AJA/784390
Kg/LKJ/385389
Kg/KKH/840965
Kg/AJA/603255
Kg/GMN/1097936
Kg/AJA/356289
Kg/AJA/336775
Kg/LKJ/736821
Kg/AJA/801342
Kg/AJA/749816
Kg/LKJ/304791
Kg/GMN/245568
Kg/KKH/392224
Kg/AJA/429984
Kg/AJA/525502
Kg/KKH/211032
Kg/LKJ/780675
Kg/AJA/477673
Kg/KKH/369174
Kg/AJA/843250
Kg/KKH/902556
Kg/AJA/1092056
Kg/AJA/315057
Kg/AJA/188261
Kg/LKJ/977988
Kg/AJA/620022
Kg/AJA/703666
Kg/LKJ/830027
Kg/LKJ/806085
Kg/AJA/1090389
Kg/AJA/778342
Kg/LKJ/496764
Kg/AJA/726258
Kg/KKH/246753
Kg/KKH/093513
Kg/LKJ/398629
Kg/KKH/971349
Kg/KKH/411384
Kg/GMN/306612
Kg/LKJ/189350
Kg/AJA/803986
Kg/LKJ/779056
Kg/KKH/377553
Kg/AJA/739744
Kg/LKJ/791396
Kg/LKJ/985909
Kg/LKJ/357857
Kg/KKH/817097
Kg/GMN/219073
Kg/AJA/267377
Kg/KKH/040951
Kg/KKH/868767
Kg/AJA/1092090
Kg/LKJ/030955
Kg/KKH/594036
Kg/KKH/696221
Kg/AJA/398759
Kg/KKH/148171
Kg/KKH/191342
Kg/AJA/1092067
Kg/AJA/153043
Kg/AJA/005189
Kg/GMN/1094966
Kg/AJA/778716
Kg/AJA/011083
Kg/LKJ/018262
Kg/AJA/874286
Kg/AJA/1092088
Kg/GMN/609044
Kg/KKH/285429
Kg/AJA/621372
Kg/LKJ/514243
Kg/AJA/983748
Kg/AJA/169610
Kg/LKJ/184384
Kg/AJA/901780
Kg/AJA/1092057
Kg/AJA/270634
Kg/AJA/221204
Kg/KKH/465727
Kg/AJA/1090497
Kg/AJA/856617
Kg/LKJ/227584
Kg/GMN/546688
Kg/LKJ/621582
Kg/LKJ/856312
Kg/LKJ/133275
Kg/AJA/805844
Kg/AJA/1090492
Kg/AJA/617943
Kg/LKJ/397316
Kg/AJA/902153
Kg/AJA/633130
Kg/AJA/254209
Kg/KKH/557863
Kg/AJA/1090465
Kg/AJA/646191
Kg/LKJ/757380
Kg/AJA/1092062
Kg/KKH/978525
Kg/AJA/915420
Kg/AJA/037734
Kg/LKJ/669826
Kg/KKH/165101
Kg/AJA/155302
Kg/LKJ/926049
Kg/AJA/1092144
Kg/AJA/1092066
Kg/AJA/404723
Kg/AJA/1090460
Kg/LKJ/404387
Kg/LKJ/305969
Kg/KKH/580000
Kg/LKJ/081205
Kg/LKJ/340264
Kg/LKJ/919463
Kg/AJA/1092073
Kg/AJA/782771
Kg/AJA/1092152
Kg/KKH/711288
Kg/AJA/146047
Kg/AJA/943136
Kg/LKJ/140734
Kg/AJA/630821
Kg/AJA/769999
Kg/AJA/806793
Kg/AJA/947494
Kg/KKH/334777
Kg/GMN/811246
Kg/LKJ/603324
Kg/AJA/737737
Kg/AJA/917825
Kg/LKJ/795465
Kg/LKJ/276511
Kg/AJA/493806
Kg/AJA/1090509
Kg/AJA/202785
Kg/LKJ/418043
Kg/AJA/681752
Kg/AJA/564710
Kg/KKH/429341
Kg/AJA/050069
Kg/AJA/368230
Kg/LKJ/917155
Kg/LKJ/846435
Kg/AJA/130374
Kg/AJA/031044
Kg/AJA/956107
Kg/AJA/247728
Kg/LKJ/301792
Kg/AJA/822905
Kg/KKH/435997
Kg/LKJ/854665
Kg/AJA/721323
Kg/LKJ/033362
Kg/AJA/249074
Kg/AJA/099312
Kg/AJA/529296
Kg/LKJ/343414
Kg/AJA/848401
Kg/LKJ/1092507
Kg/AJA/519393
Kg/AJA/240678
Kg/GMN/504904
Kg/AJA/363605
Kg/KKH/610776
Kg/GMN/399834
Kg/LKJ/101195
Kg/KKH/563953
Kg/AJA/923826
Kg/AJA/1092076
Kg/AJA/859279
Kg/KKH/003592
Kg/AJA/1095869
Kg/AJA/111305
Kg/AJA/562725
Kg/LKJ/226071
Kg/AJA/034051
Kg/LKJ/581363
Kg/AJA/221605
Kg/AJA/593578
Kg/AJA/555536
Kg/LKJ/966602
Kg/AJA/672849
Kg/KKH/386254
Kg/LKJ/823403
Kg/AJA/564363
Kg/AJA/786894
Kg/KKH/405420
Kg/GMN/481954
Kg/AJA/1090386
Kg/AJA/756752
Kg/KKH/797704
Kg/AJA/995632
Kg/KKH/528962
Kg/AJA/629413
Kg/AJA/552434
Kg/LKJ/217526
Kg/AJA/064189
Kg/LKJ/771462
Kg/GMN/195005
Kg/LKJ/744206
Kg/AJA/069618
Kg/AJA/360405
Kg/GMN/216889
Kg/LKJ/942802
Kg/KKH/562866
Kg/AJA/312537
Kg/AJA/533439
Kg/AJA/1092071
Kg/AJA/1090450
Kg/AJA/038329
Kg/KKH/427687
Kg/AJA/709985
Kg/AJA/791166
Kg/AJA/351492
Kg/AJA/236507
Kg/AJA/1095874
Kg/AJA/1090484
Kg/AJA/506447
Kg/AJA/638009
Kg/AJA/1095867
Kg/LKJ/1098432
Kg/KKH/884758
Kg/LKJ/153973
Kg/KKH/206583
Kg/KKH/973865
Kg/AJA/130207
Kg/KKH/537422
Kg/LKJ/906760
Kg/AJA/1090381
Kg/LKJ/057283
Kg/AJA/861284
Kg/LKJ/121341
Kg/LKJ/500480
Kg/LKJ/942288
Kg/AJA/224034
Kg/GMN/130064
Kg/AJA/1092137
Kg/AJA/727847
Kg/AJA/513144
Kg/AJA/955504
Kg/LKJ/1092189
Kg/AJA/921609
Kg/LKJ/097381
Kg/AJA/073973
Kg/AJA/268459
Kg/AJA/786695
Kg/AJA/1092130
Kg/LKJ/455108
Kg/LKJ/630918
Kg/KKH/466355
Kg/AJA/687741*/

/*"Kg/AJA/296552",
"Kg/LKJ/729859",
"Kg/KKH/735887",
"Kg/AJA/1090390",
"Kg/AJA/858255",
"Kg/LKJ/157063",
"Kg/KKH/764085",
"Kg/AJA/1094516",
"Kg/AJA/960050",
"Kg/AJA/600829",
"Kg/AJA/053885",
"Kg/LKJ/115078",*/

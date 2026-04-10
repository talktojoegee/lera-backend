<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class GISQuickFixWithFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:gisfile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $properties = PropertyList::/*whereNotNull('distribution_code')->*/orderBy('id', 'DESC')->get();

       /*
        *  foreach ($properties as $property){
            $propertyId = $property->building_code;
            $result = DB::connection('pgsql')->selectOne("
    WITH intersected_cells AS (
        SELECT
            lp.prop_id,
            ec.enum_cell_number,
            lp.lga_abb,
            lp.lga,
            CASE
                WHEN ec.enum_cell_number ILIKE '%' || lp.lga_abb || '%' THEN 1
                WHEN ec.enum_cell_number ILIKE '%' || lp.lga || '%' THEN 2
                ELSE 3
            END AS match_rank
        FROM
            public.\"Land_Admin_New_Form\" lp
        JOIN
            public.enumeration_cells ec
            ON ST_Intersects(lp.geom, ec.geom)
        WHERE
            lp.completeness_status = 'Complete'
    ),
    ranked_cells AS (
        SELECT *,
            ROW_NUMBER() OVER (PARTITION BY prop_id ORDER BY match_rank, enum_cell_number) AS rn
        FROM intersected_cells
    )
    SELECT
        prop_id,
        enum_cell_number
    FROM
        ranked_cells
    WHERE
        rn = 1
        AND prop_id = ?
    ORDER BY
        enum_cell_number
", [$propertyId]);
            if(!empty($result)){
                $property->distribution_code = $result->enum_cell_number;
                $property->save();
                echo "Property ID:: $propertyId Distribution Code:: $result->enum_cell_number \n";
            }
        } */
        foreach ($properties as $property){
            $propertyId = $property->building_code;
            $result = DB::connection('pgsql')->selectOne("
    WITH intersected_cells AS (
        SELECT
            lp.prop_id,
            ec.enum_cell_number,
            lp.lga_abb,
            lp.lga,
            ST_Area(ec.geom) AS cell_area,
            CASE
                WHEN ec.enum_cell_number ILIKE '%' || lp.lga_abb || '%' THEN 1
                WHEN ec.enum_cell_number ILIKE '%' || lp.lga || '%' THEN 2
                ELSE 3
            END AS match_rank
        FROM
            public.\"Land_Admin_New_Form\" lp
        JOIN
            public.enumeration_cells ec
            ON ST_Intersects(lp.geom, ec.geom)
        WHERE
            lp.completeness_status = 'Complete'
    ),
    ranked_cells AS (
        SELECT *,
            ROW_NUMBER() OVER (PARTITION BY prop_id ORDER BY match_rank, cell_area ASC, enum_cell_number) AS rn
        FROM intersected_cells
    )
    SELECT
        prop_id,
        enum_cell_number
    FROM
        ranked_cells
    WHERE
        rn = 1
        AND prop_id = ?
    ORDER BY
        enum_cell_number
", [$propertyId]);
            if(!empty($result)){
                $property->distribution_code = $result->enum_cell_number;
                //$property->save();
                echo "Property ID:: $propertyId Distribution Code:: $result->enum_cell_number \n";
            }
        }



        /*$path =  storage_path('app/public/deleteBillsProperties.csv');
        $reader =  Reader::createFromPath($path, 'r');
        $reader->setDelimiter(",");
        $first = true;
        $counter = 0;
        $updateCounter = 0;*/
        //foreach ($reader->getRecords() as $key => $row) {
           /* if ($first) {
                $first = false;
                continue;
            }*/
           /* if(!empty($row[0]) ){
                $bill = Billing::where('building_code', $row[0])->first();
                if(!empty($bill)){
                    $bill->delete();
                    echo "Bill {$row[0]} deleted \n";
                }
                $property = PropertyList::where('building_code', $row[0])->first();
                if(!empty($property)){
                    $property->delete();
                    echo "Property {$row[0]} deleted \n";
                }

            }*/

        //}
        /*
            $record = DB::connection('pgsql')
                ->table('Land_Admin_New_Form')
                ->where('completeness_status', 'Complete')
                ->where('bill_sync', 1)
                ->where('prop_id', $row[1])
                ->first();

            if(!empty($record)){
                $property = PropertyList::where('building_code', $row[1])->first();
                if(!empty($property)){
                   // $charge = $this->_getChargeRate($record->occupier_s, $record->landuse);
                    $property->ba = $row[5];// $record->compute_area_from_bfp ?? 200;
                    //$property->cr = $charge->id ?? 1;
                    $property->save();
                    $counter++;
                    echo "Item:: $counter \n";
                }
            }
            echo $key.":: Property ID:: $row[1] \n";
        }*/


        //Another operation
       /* foreach ($reader->getRecords() as $key => $row) {
            if ($first) {
                $first = false;
                continue;
            }

            $bill = Billing::where('building_code', $row[1])->first();
            if(!empty($bill)){
                $bill->delete();
                $counter++;
                echo "Item:: ".$counter." deleted \n";
            }
            $property = PropertyList::where('building_code', $row[1])->first();
            if(!empty($property)){
                $property->ba = $row[3];
                $property->save();
                $updateCounter++;
                echo "Item:: ".$updateCounter." updated \n";
            }
        }*/

    }





    public function _getChargeRate($occupier, $landUse){
        $normalizedClassName = trim($occupier);
        switch($landUse){
            case 1: //
                return ChargeRate::first();
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
}

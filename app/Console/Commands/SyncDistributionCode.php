<?php

namespace App\Console\Commands;

use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDistributionCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-distribution-code';

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
        $counter = 0;
        $properties = PropertyList::whereNull('distribution_code')
            ->orderBy('id', 'DESC')
            ->get();
        $total = $properties->count();
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
    FROM public.\"Land_Admin_New_Form\" lp
    JOIN public.enumeration_cells ec
        ON ST_Intersects(lp.geom, ec.geom)
    WHERE lp.completeness_status = 'Complete'
      AND lp.prop_id = ?
),
ranked_cells AS (
    SELECT *,
        ROW_NUMBER() OVER (
            PARTITION BY prop_id
            ORDER BY cell_area ASC, match_rank, enum_cell_number
        ) AS rn
    FROM intersected_cells
)
SELECT prop_id, enum_cell_number
FROM ranked_cells
WHERE rn = 1
", [$propertyId]);
            if(!empty($result)){
                $property->distribution_code = $result->enum_cell_number;
                $property->save();
                $property->tracker = 1;
                $counter++;
                echo "Property ID:: $propertyId | Distribution Code:: $result->enum_cell_number | Item:: $counter | Total:: $total \n";
            }
        }
        echo "Distribution Code Sync Completed!";


    }
}

<?php

namespace App\Console\Commands;

use App\Models\PropertyList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GISQuickFix extends Command
{
    public $lgaId = 0;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gis:quickfix';

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
        DB::connection('pgsql')
            ->table('Land_Admin_New_Form')
            ->when($this->lgaId > 0, function($query) {
                return $query->where('lga_id', $this->lgaId);
            })
            ->where('completeness_status', 'Complete')
            ->where('bill_sync', 0)
            ->cursor()
            ->each(function($record){
                $property = PropertyList::where('building_code', $record->prop_id)->first();
                if(!empty($property)){
                    //$property->image = $record->photo_link ?? '';
                    $property->ba = $record->area_from_bfp ?? '';
                    $property->save();
                }
            });
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use Illuminate\Console\Command;

class UpdateBillClassId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:updateBillClassId';

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
        $properties = PropertyList::all();
        foreach ($properties as $property) {
            $bill = Billing::where('building_code', $property->building_code)->first();
            if (! empty($bill)) {
                $class = PropertyClassification::where(
                    'class_name',
                    'LIKE',
                    '%'.$property->class_name.'%'
                )->first();
                if (! empty($class)) {
                    $bill->class_id = $class->id;
                    $bill->save();
                    echo "Bill Assessment No.: $bill->assessment_no | Bill Class ID: $bill->class_id \n";
                }
            }
        }
    }
}

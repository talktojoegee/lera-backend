<?php

namespace App\Console\Commands;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\PrintBillLog;
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

        //Mark as printed

        /*$printed = PrintBillLog::all();
        foreach($printed as $log){
            $bill = Billing::where('assessment_no', $log->assessment_no)->first();
            if($bill){
                $bill->printed = 1;
                $bill->save();
                echo "Bill Assessment No.: $log->assessment_no | Bill printed! \n";
            }
        }*/

        /*
         * Update bills paid_amount column with the discounted amount
         */
        $paymentLogs = BillPaymentLog::where('is_discount', 1)->get();
        foreach($paymentLogs as $log){
            $bill = Billing::where('assessment_no', $log->assessment_no)->first();
            if($bill){
                echo "Bill Assessment No.: $log->assessment_no | Amount: $log->amount | Paid Amount: $bill->paid_amount \n";
                $bill->paid_amount += $log->amount;
                $bill->save();
            }
        }
        echo " Action completed! \n";
        /*
         * Updates virtualization table on GIS  with paid date and status
         *
         */
        /*$paidBills = Billing::where('paid', 1)->get();
        if(count($paidBills) > 0){
            foreach ($paidBills as $bill){
                    DB::connection('pgsql')
                        ->table('virtualization')
                        ->where('assessment_code', $bill->assessment_no)
                        ->update(['bill_paid' => 1, 'bill_paid_date' => $bill->date_paid ]);
            }
        }*/
        /*
         * DB::connection('pgsql')
            ->table('Land_Admin_New_Form')
            ->when($this->lgaId > 0, function($query) {
                return $query->where('lga_id', $this->lgaId);
            })
            ->where('completeness_status', 'Complete')
            ->where('bill_sync', 1)
            ->cursor()
            ->each(function($record){
                $property = PropertyList::where('building_code', $record->prop_id)->first();
                if(!empty($property)){
                    //$property->image = $record->photo_link ?? '';
                    $property->image = $record->photo_link ?? '';
                    $property->save();
                    echo "Building code:: $property->building_code Image URL updated! \n";
                }
            });
        */
    }
}

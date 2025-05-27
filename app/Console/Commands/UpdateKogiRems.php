<?php

namespace App\Console\Commands;

use App\Jobs\NotifyKogiRemsJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\KogiRemsNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateKogiRems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:updateKRems';

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
        $payments = BillPaymentLog::all();
        foreach($payments as $payment){
            $notify = KogiRemsNotification::where('transRef', $payment->trans_ref)->first();
            if(empty($notify)){
                $bill = Billing::where('assessment_no', $payment->assessment_no)->first();
                if(!empty($bill)){
                    KogiRemsNotification::create([
                        "assessmentno"=>$payment->assessment_no,
                        "buildingcode"=>$payment->building_code,
                        "kgtin"=>$payment->kgtin ?? null,
                        "name"=>$payment->customer_name ?? null,
                        "amount"=>$payment->amount ?? null,
                        "phone"=>'234',
                        "email"=>$payment->email ?? null,
                        "transdate"=>Carbon::parse($payment->entry_date)->format('Y-m-d') ?? now(),
                        "transRef"=>$payment->trans_ref,
                        "paymode"=>$payment->pay_mode ?? null ,
                        "bank_name"=>$payment->bank_name ?? null,
                        "luc_amount"=>$bill->bill_amount,
                    ]);
                }



            }
        }
        NotifyKogiRemsJob::dispatch();
    }
}

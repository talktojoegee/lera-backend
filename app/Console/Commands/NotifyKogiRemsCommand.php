<?php

namespace App\Console\Commands;

use App\Models\KogiRemsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class NotifyKogiRemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:kogirems';

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
        $notifications = KogiRemsNotification::where('status', 0)->orderBy('id', 'DESC')->get();
        foreach ($notifications as $notification){
            $assessment_no = strtoupper($notification->assessmentno);
            $buildingcode = strtoupper($notification->buildingcode);
            $kgtin = $notification->kgtin;
            $name = $notification->name;
            $amount = $notification->amount;
            $phone = $notification->phone;
            $email = $notification->email;
            $transdate = $notification->transdate;
            $transRef = $notification->transRef;
            $lucAmount = $notification->luc_amount ?? 0;
            $response = Http::get("https://kogiirs.aoctms.com.ng/luc/default.asp?assessmentno={$assessment_no}&buildingcode={$buildingcode}&kgtin={$kgtin}&name={$name}&amount={$amount}&phone={$phone}&email={$email}&transdate={$transdate}&transRef={$transRef}&paymode=POS&lucAmount={$lucAmount}");
            if($response->successful()){
                $notification->status = 1;
                $notification->save();
            }else{
                $notification->status = 2;
                $notification->save();
            }

        }

    }
}

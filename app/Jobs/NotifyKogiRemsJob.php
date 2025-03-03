<?php

namespace App\Jobs;

use App\Models\KogiRemsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class NotifyKogiRemsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
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
            $response = Http::get("https://kogiirs.aoctms.com.ng/luc/default.asp?assessmentno={$assessment_no}&buildingcode={$buildingcode}&kgtin={$kgtin}&name={$name}&amount={$amount}&phone={$phone}&email={$email}&transdate={$transdate}&transRef={$transRef}&paymode=POS");
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

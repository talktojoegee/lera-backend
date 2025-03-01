<?php

namespace App\Jobs;

use App\Exports\BillingExport;
use App\Models\User;
use App\Notifications\BillingExportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ExportBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $type;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $type)
    {
        $this->userId = $userId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $user = User::find($this->userId);

        if (!$user) {
            return;
        }

        $fileName = "exports/billings_{$this->userId}_".time().".xlsx";
        Excel::store(new BillingExport($this->userId, $this->type), $fileName, 'public');

        // Notify user via email or database notification
        $user->notify(new BillingExportCompleted($fileName));
    }

}

<?php

namespace App\Jobs;

use App\Imports\BulkForceSyncImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class BulkForceSynchronization implements ShouldQueue
{
    use Queueable;

    protected $filePath, $authUser, $header;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $authUser, $header)
    {
        $this->filePath = $filePath;
        $this->authUser = $authUser;
        $this->header = $header;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Excel::import(new BulkForceSyncImport($this->authUser), storage_path('app/public/' . $this->filePath));
        //Storage::delete($this->filePath);
    }
}


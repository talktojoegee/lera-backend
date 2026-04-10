<?php

namespace App\Providers;

use App\Models\ExportRecord;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function ($event) {
            $command = $event->job->payload()['data']['command'] ?? null;
            if (!$command) return;

            $object = unserialize($command);
            if (property_exists($object, 'exportRecordId')) {
                ExportRecord::where('id', $object->exportRecordId)
                    ->update([
                        'status' => 'processing',
                        'message' => 'Export is currently being generated...',
                    ]);
            }
        });

        Queue::after(function ($event) {
            $command = $event->job->payload()['data']['command'] ?? null;
            if (!$command) return;

            $object = unserialize($command);
            if (property_exists($object, 'exportRecordId')) {
                ExportRecord::where('id', $object->exportRecordId)
                    ->update([
                        'status' => 'completed',
                        'message' => 'Export completed successfully.',
                    ]);
            }
        });
    }
}

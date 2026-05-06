<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\SynchronizationLog;
use App\Models\User;
use App\Traits\PropertySyncTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDataCommand extends Command
{
    use PropertySyncTrait;

    // Optional: Set these properties if logging sync activity to database
    public $lgaId = null;
    public $userId = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:syncData {--property-ids= : Comma-separated list of property IDs to sync (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize property data from remote enumeration to local property lists';

    /**
     * Execute the console command.
     *
     * Synchronizes all complete enumeration records. Use --property-ids to sync specific properties.
     */
    public function handle(): void
    {
        try {
            // Get property IDs from option if provided, otherwise sync all complete records
            $propertyIds = null;

            if ($this->option('property-ids')) {
                $propertyIds = array_map('trim', explode(',', $this->option('property-ids')));
                $this->info("Syncing specific property IDs: " . implode(', ', $propertyIds));
            } else {
                $this->info("Syncing all complete enumeration records...");
            }

            // Use PropertySyncTrait to process records
            $stats = $this->syncPropertyData($propertyIds ?? [], function ($message) {
                $this->info($message);
            });

            // Log completion
            $this->info("");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✓ Synchronization Complete");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Total Processed: {$stats['total']}");
            $this->info("Successfully Synced: {$stats['synced']}");
            $this->info("Exceptions: {$stats['exceptions']}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // Log activity if userId is available
            $this->_logActivity($stats);

        } catch (\Exception $e) {
            Log::error('SyncDataCommand failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Synchronization failed: ' . $e->getMessage());
        }

    }

    /**
     * Log synchronization activity
     */
    private function _logActivity($stats = []): void
    {
        // Log activity if userId is available
        if (!isset($this->userId)) {
            return;
        }

        $user = User::find($this->userId);
        if (empty($user)) {
            return;
        }

        $title = "Property Synchronization";
        $narration = "{$user->name} synchronized {$stats['synced']} properties. "
                   . "{$stats['exceptions']} went into exception. "
                   . "Total processed: {$stats['total']}.";

        try {
            ActivityLog::LogActivity($title, $narration, $user->id);
            SynchronizationLog::logSyncReport($stats['total'], $stats['synced'], now(), $this->lgaId ?? 12);
        } catch (\Exception $e) {
            Log::error('Failed to log sync activity', ['message' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->_logActivity($this->getSyncStats());
    }
}


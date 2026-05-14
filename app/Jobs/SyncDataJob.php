<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\SynchronizationLog;
use App\Models\User;
use App\Traits\UtilityTrait;
use App\Traits\PropertySyncTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDataJob implements ShouldQueue
{
    use Queueable, UtilityTrait, PropertySyncTrait;

    public $lgaId;
    public $userId;
    public $tries = 10;
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct($lga, $userId)
    {
        $this->lgaId = $lga;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     * Uses PropertySyncTrait to handle all synchronization logic
     */
    public function handle(): void
    {
        try {
            // Get all property IDs from the remote source
            $propertyIds = $this->_getPropertyIdsToSync();

            Log::info('SyncDataJob: Starting sync', [
                'lga_id' => $this->lgaId,
                'property_count' => count($propertyIds),
                'user_id' => $this->userId
            ]);

            $stats = $this->syncPropertyData($propertyIds, function($message) {
                Log::info('SyncDataJob: ' . $message);
            });
            $this->_logActivity($stats);
            Log::info('SyncDataJob: Sync completed successfully', $stats);

        } catch (\Exception $e) {
            Log::error('SyncDataJob: Sync failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'lga_id' => $this->lgaId,
                'user_id' => $this->userId
            ]);

            // Still log activity even on failure
            $this->_logActivity([
                'synced' => $this->syncCounter ?? 0,
                'exceptions' => $this->exceptionCounter ?? 0,
                'total' => $this->processedCounter ?? 0
            ]);
        }
    }

    /**
     * Get property IDs from remote source to sync
     * Can filter by LGA if specified
     *
     * @return array Array of property IDs
     */
    private function _getPropertyIdsToSync(): array
    {
        try {
            $query = DB::connection('pgsql')
                ->table('lekki_enumeration')
                ->where('completeness_status', 'Complete')
                ->select('property_id');
            if ($this->lgaId > 0) {
                $lga = \App\Models\Lga::find($this->lgaId);
                if ($lga) {
                    $query->where('lga', 'LIKE', "%{$lga->lga_name}%");
                }
            }
            return $query->pluck('property_id')->toArray();

        } catch (\Exception $e) {
            Log::error('SyncDataJob: Failed to get property IDs', [
                'message' => $e->getMessage(),
                'lga_id' => $this->lgaId
            ]);
            return [];
        }
    }

    /**
     * Log synchronization activity
     *
     * @param array $stats Synchronization statistics
     */
    private function _logActivity(array $stats = []): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                return;
            }

            $title = "Background Property Synchronization";
            $narration = "{$user->name} completed background sync job. "
                       . "Synced: {$stats['synced']} properties. "
                       . "Exceptions: {$stats['exceptions']}. "
                       . "Total processed: {$stats['total']}.";

            ActivityLog::LogActivity($title, $narration, $user->id);

            // Log sync report
            SynchronizationLog::logSyncReport(
                $stats['total'] ?? 0,
                $stats['synced'] ?? 0,
                now(),
                $this->lgaId
            );

            Log::info('SyncDataJob: Activity logged', [
                'user_id' => $user->id,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('SyncDataJob: Failed to log activity', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle failed job
     */
    public function failed(Throwable $e): void
    {
        Log::error('SyncDataJob: Job failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->_logActivity([
            'synced' => $this->syncCounter ?? 0,
            'exceptions' => $this->exceptionCounter ?? 0,
            'total' => $this->processedCounter ?? 0
        ]);
    }
}






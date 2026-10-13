<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\SynchronizationLog;
use App\Models\User;
use App\Traits\PropertySyncTrait;
use App\Traits\UtilityTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPropertyStreetNameCommand extends Command
{
    use PropertySyncTrait, UtilityTrait;

    public $lgaId = null;

    public $userId = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-street-names
                            {--property-ids= : Comma-separated building codes / property IDs to update (optional)}
                            {--dry-run : Preview updates without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update property_lists and billings street_name from remote lekki_enumeration records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $propertyIds = [];

            if ($this->option('property-ids')) {
                $propertyIds = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) $this->option('property-ids'))
                )));
                $this->info('Updating street names for property IDs: '.implode(', ', $propertyIds));
            } else {
                $this->info('Updating street names for all complete enumeration records...');
            }

            if ($this->option('dry-run')) {
                $this->warn('Dry run enabled — no database changes will be saved.');
            }

            $stats = $this->syncPropertyStreetNames(
                $propertyIds,
                fn (string $message) => $this->line($message),
                (bool) $this->option('dry-run')
            );

            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info($this->option('dry-run') ? 'Street Name Sync Preview Complete' : 'Street Name Sync Complete');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Total Processed: {$stats['total']}");
            $this->info("Properties Updated: {$stats['updated']}");
            $this->info("Billings Updated: {$stats['billings_updated']}");
            $this->info("Unchanged: {$stats['unchanged']}");
            $this->info("Skipped (no local property): {$stats['skipped']}");
            $this->info("Exceptions: {$stats['exceptions']}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $this->logActivity($stats);

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('SyncPropertyStreetNameCommand failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Street name sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array{total: int, updated: int, unchanged: int, skipped: int, exceptions: int}  $stats
     */
    private function logActivity(array $stats = []): void
    {
        if ($this->userId === null) {
            return;
        }

        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $title = 'Property Street Name Sync';
        $narration = "{$user->name} updated street names on {$stats['updated']} properties. "
            ."{$stats['skipped']} skipped (no local match). "
            ."{$stats['unchanged']} unchanged. "
            ."Total processed: {$stats['total']}.";

        try {
            ActivityLog::LogActivity($title, $narration, $user->id);
            SynchronizationLog::logSyncReport($stats['total'], $stats['updated'], now(), $this->lgaId ?? 12);
        } catch (Throwable $e) {
            Log::error('Failed to log street name sync activity', ['message' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->logActivity($this->getStreetNameSyncStats());
    }
}

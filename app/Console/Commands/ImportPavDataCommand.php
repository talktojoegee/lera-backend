<?php

namespace App\Console\Commands;

use Database\Seeders\PavLoadDataSeeder;
use Illuminate\Console\Command;

class ImportPavDataCommand extends Command
{
    protected $signature = 'pav:import
                            {--file= : Path to pav_load_data.csv (default: project root)}';

    protected $description = 'Load property_assessment_values from pav_load_data.csv (required before billing)';

    public function handle(): int
    {
        $file = trim((string) $this->option('file'));
        if ($file !== '' && ! is_file($file)) {
            $this->error("CSV not found: {$file}");

            return self::FAILURE;
        }

        $defaultPath = base_path('pav_load_data.csv');
        if ($file === '' && ! is_file($defaultPath)) {
            $this->error("CSV not found: {$defaultPath}");
            $this->line('Deploy pav_load_data.csv to the server root, or pass --file=/path/to/pav_load_data.csv');

            return self::FAILURE;
        }

        $this->info('Importing PAV data from '.($file !== '' ? $file : $defaultPath).'...');

        $seeder = new PavLoadDataSeeder;
        if ($file !== '') {
            $seeder->csvPath = $file;
        }
        $seeder->setCommand($this);
        $seeder->run();

        $count = \App\Models\PropertyAssessmentValue::count();
        $this->info("Done. property_assessment_values row count: {$count}");

        if ($count === 0) {
            $this->warn('No rows imported — check the CSV format and file path.');

            return self::FAILURE;
        }

        $this->table(
            ['property_type', 'pav_code', 'annual_due'],
            \App\Models\PropertyAssessmentValue::query()
                ->orderBy('pav_code')
                ->get(['property_type', 'pav_code', 'annual_due'])
                ->map(fn ($row) => [$row->property_type, $row->pav_code, $row->annual_due])
                ->all()
        );

        $this->newLine();
        $this->line('Next: backfill property pav_code rows, then re-run billing:');
        $this->line('  php artisan properties:backfill-pav');
        $this->line('  php artisan tinker --execute="(new App\\Jobs\\ProcessBillingJob(1, 2026, 1))->dispatch();"');

        return self::SUCCESS;
    }
}

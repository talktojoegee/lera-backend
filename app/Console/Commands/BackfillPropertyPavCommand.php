<?php

namespace App\Console\Commands;

use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
use App\Support\PropertyTypeMapper;
use Illuminate\Console\Command;

class BackfillPropertyPavCommand extends Command
{
    protected $signature = 'properties:backfill-pav
                            {--lga= : Only properties for this LGA id}
                            {--dry-run : Show changes without saving}';

    protected $description = 'Set pav_code on property_lists from property_assessment_values (after pav:import)';

    public function handle(): int
    {
        if (PropertyAssessmentValue::count() === 0) {
            $this->error('property_assessment_values is empty. Run: php artisan pav:import');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $lgaId = $this->option('lga');

        $query = PropertyList::query()->orderBy('id');
        if ($lgaId !== null && $lgaId !== '') {
            $query->where('lga_id', (int) $lgaId);
        }

        $updated = 0;
        $unmatched = 0;
        $unchanged = 0;

        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        foreach ($query->cursor() as $property) {
            $bar->advance();

            $pav = PropertyTypeMapper::findPavForProperty($property);
            if ($pav === null) {
                $unmatched++;

                continue;
            }

            if ((string) ($property->pav_code ?? '') === (string) $pav->pav_code) {
                $unchanged++;

                continue;
            }

            if (! $dryRun) {
                $property->update(['pav_code' => $pav->pav_code]);
            }
            $updated++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Count'], [
            ['Updated', $updated],
            ['Unchanged', $unchanged],
            ['No PAV match', $unmatched],
        ]);

        if ($dryRun) {
            $this->warn('Dry run — no rows saved.');
        }

        return $unmatched > 0 && $updated === 0 ? self::FAILURE : self::SUCCESS;
    }
}

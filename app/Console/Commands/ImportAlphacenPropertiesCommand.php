<?php

namespace App\Console\Commands;

use App\Models\ChargeRate;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use App\Support\PropertyTypeMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;
use Throwable;

class ImportAlphacenPropertiesCommand extends Command
{
    protected $signature = 'alphacen:import-properties --file=lera_2.csv
                            {--file=New_Alphacen_list.csv : CSV filename or absolute path}
                            {--dry-run : Validate rows without writing to the database}
                            {--skip-existing : Only insert new properties (skip rows that already exist)}';

    protected $description = 'Import Alphacen property enumeration CSV into property_lists (LGA 2)';

    private const DEFAULT_AREA = '8093720';

    private const DEFAULT_BA = 200;

    private const DEFAULT_BOREHOLE = 0;

    private const DEFAULT_LGA_ID = 2;

    private int $processed = 0;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $path = $this->resolveCsvPath((string) $this->option('file'));
        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $this->info('Alphacen property import');
        $this->line("File: {$path}");
        $this->line('Mode: '.($dryRun ? 'dry-run' : ($skipExisting ? 'insert-only' : 'upsert')));

        try {
            $reader = Reader::createFromPath($path, 'r');
            $reader->setHeaderOffset(0);

            $records = Statement::create()->process($reader);
            $bar = $this->output->createProgressBar(iterator_count($records));
            $bar->start();

            $reader = Reader::createFromPath($path, 'r');
            $reader->setHeaderOffset(0);
            $records = Statement::create()->process($reader);

            foreach ($records as $row) {
                $this->processed++;
                $bar->advance();

                try {
                    $this->importRow($row, $dryRun, $skipExisting);
                } catch (Throwable $e) {
                    $this->failed++;
                    $buildingCode = trim((string) ($row['property_id'] ?? ''));
                    Log::error('Alphacen import: row failed', [
                        'building_code' => $buildingCode,
                        'error' => $e->getMessage(),
                    ]);
                    $this->newLine();
                    $this->warn("Failed [{$buildingCode}]: {$e->getMessage()}");
                }
            }

            $bar->finish();
            $this->newLine(2);
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], [
            ['Processed', $this->processed],
            ['Created', $this->created],
            ['Updated', $this->updated],
            ['Skipped', $this->skipped],
            ['Failed', $this->failed],
        ]);

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function importRow(array $row, bool $dryRun, bool $skipExisting): void
    {
        $buildingCode = trim((string) ($row['property_id'] ?? ''));
        if ($buildingCode === '') {
            $this->skipped++;

            return;
        }

        $existing = PropertyList::where('building_code', $buildingCode)->first();
        if ($skipExisting && $existing !== null) {
            $this->skipped++;

            return;
        }

        $propertyData = $this->buildPropertyData($row);

        if ($dryRun) {
            $existing === null ? $this->created++ : $this->updated++;

            return;
        }

        if ($existing === null) {
            PropertyList::create($propertyData);
            $this->created++;

            return;
        }

        $existing->update($propertyData);
        $this->updated++;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function buildPropertyData(array $row): array
    {
        $streetAddress = trim((string) ($row['street_address'] ?? ''));
        $houseNumber = trim((string) ($row['house_number'] ?? ''));
        $composedAddress = trim($houseNumber.' '.$streetAddress);

        $rawPropertyType = trim((string) ($row['property_type'] ?? ''));
        $normalizedPropertyType = PropertyTypeMapper::normalize($rawPropertyType);
        $syncWord = $normalizedPropertyType !== '' ? $normalizedPropertyType : 'residential';

        $businessName1 = trim((string) ($row['business_name_1'] ?? ''));
        if ($businessName1 === '') {
            $businessName1 = trim((string) ($row['business_type'] ?? ''));
        }

        $chargeRate = $this->resolveChargeRate($businessName1, $rawPropertyType) ?: ChargeRate::first();
        $propertyClassification = ($chargeRate && (int) $chargeRate->id === 7)
            ? PropertyClassification::find(2)
            : PropertyClassification::find(1);

        $pavRecord = PropertyTypeMapper::findPav($normalizedPropertyType, $syncWord, $syncWord);

        $areaSqm = trim((string) ($row['area_sqm'] ?? ''));

        return [
            'building_code' => trim((string) ($row['property_id'] ?? '')),
            'street_name' => $streetAddress,
            'property_address' => $streetAddress,
            'address' => $composedAddress !== '' ? $composedAddress : $streetAddress,
            'assigned_house_number' => $houseNumber,
            'confirmed_house_number' => $houseNumber,
            'property_type' => $normalizedPropertyType !== '' ? $normalizedPropertyType : null,
            'business_type' => trim((string) ($row['business_type'] ?? '')) ?: null,
            'business_name_1' => $businessName1,
            'business_name_2' => trim((string) ($row['business_name_2'] ?? '')),
            'business_name_3' => trim((string) ($row['business_name_3'] ?? '')),
            'business_name_4' => trim((string) ($row['business_name_4'] ?? '')),
            'owner_name' => trim((string) ($row['owner_name'] ?? '')) ?: null,
            'owner_gsm' => trim((string) ($row['owner_phone'] ?? '')) ?: null,
            'owner_email' => trim((string) ($row['owner_email'] ?? '')) ?: null,
            'no_of_floors' => $this->stringOrNull($row['no_of_floors'] ?? null),
            'no_of_units' => $this->stringOrNull($row['number_of_units'] ?? null),
            'revenue_category' => trim((string) ($row['revenue_category'] ?? '')) ?: null,
            'contact_name' => trim((string) ($row['contact_name'] ?? '')),
            'contact_phone' => trim((string) ($row['contact_phone'] ?? '')),
            'contact_email' => trim((string) ($row['contact_email'] ?? '')),
            'contact_address' => trim((string) ($row['contact_address'] ?? '')),
            'image' => trim((string) ($row['photo_url'] ?? '')) ?: null,
            'latitude' => $this->numericOrNull($row['latitude'] ?? null),
            'longitude' => $this->numericOrNull($row['longitude'] ?? null),
            'area_in_me' => $areaSqm !== '' ? $areaSqm : null,
            'confidence' => trim((string) ($row['confidence'] ?? '')),
            'area' => self::DEFAULT_AREA,
            'ba' => self::DEFAULT_BA,
            'borehole' => self::DEFAULT_BOREHOLE,
            'lga_id' => self::DEFAULT_LGA_ID,
            'class_name' => $propertyClassification->class_name ?? '',
            'class_id' => (int) ($propertyClassification->id ?? $this->resolveCommercialClassId($businessName1, $rawPropertyType)),
            'pav_code' => $pavRecord->pav_code ?? null,
            'zone_name' => $pavRecord->zones ?? 'A',
            'sub_zone' => $pavRecord->zones ?? 'A1',
            'ward' => $pavRecord->zones ?? 'A1',
            'sync_word' => $syncWord,
            'property_use' => $syncWord,
            'cr' => $chargeRate->id ?? 1,
            'dep_id' => 1,
            'power' => 0,
            'water' => 0,
            'building_age' => 0,
            'actual_age' => 0,
            'property_name' => $businessName1 !== '' ? $businessName1 : 'N/A',
            'occupant' => trim((string) ($row['owner_name'] ?? '')) ?: null,
            'occupier' => trim((string) ($row['owner_name'] ?? '')) ?: null,
        ];
    }

    protected function normalizePropertyType(string $value): string
    {
        return PropertyTypeMapper::normalize($value);
    }

    protected function resolveChargeRate(?string $businessName, ?string $propertyType): ?ChargeRate
    {
        if (
            (! empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (! empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return ChargeRate::find(7);
        }

        return ChargeRate::first();
    }

    protected function resolveCommercialClassId(?string $businessName, ?string $propertyType): int
    {
        if (
            (! empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (! empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return 2;
        }

        return 1;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function numericOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' && is_numeric($value) ? $value : null;
    }

    protected function resolveCsvPath(string $file): string
    {
        if ($file === '') {
            $file = 'New_Alphacen_list.csv';
            //$file = 'LERA_property_data_updated_2026-06-04.csv';
        }

        if (str_starts_with($file, '/') || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        $candidates = [
            base_path($file),
            storage_path('app/'.$file),
            storage_path('app/public/'.$file),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return base_path($file);
    }
}

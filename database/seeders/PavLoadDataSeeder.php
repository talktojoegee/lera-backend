<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PavLoadDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Reads pav_load_data.csv located at the project root (base_path('pav_load_data.csv'))
     * and inserts/updates rows into the property_assessment_values table.
     *
     * @return void
     */
    public function run()
    {
        $csvPath = base_path('pav_load_data.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        if (($handle = fopen($csvPath, 'r')) === false) {
            $this->command->error("Unable to open CSV file: {$csvPath}");
            return;
        }

        $rowIndex = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;

            // Skip header
            if ($rowIndex === 1) {
                continue;
            }

            // CSV columns: id,pav_code,zones,property_type,annual_due,created_at,updated_at
            $pavCode = isset($row[1]) ? trim($row[1]) : null;
            $zones = isset($row[2]) ? trim($row[2]) : null;
            $propertyType = isset($row[3]) ? trim($row[3]) : null;
            $annualRaw = isset($row[4]) ? trim($row[4]) : null;
            $createdRaw = isset($row[5]) ? trim($row[5]) : null;
            $updatedRaw = isset($row[6]) ? trim($row[6]) : null;

            if (!$pavCode) {
                // skip rows without a key
                continue;
            }

            // Normalize annual_due: remove commas, spaces, currency symbols
            $annualClean = preg_replace('/[^0-9\.\-]/', '', $annualRaw);
            // If cleaned value is empty or just a dash, treat as 0
            if ($annualClean === '' || $annualClean === '-' || $annualClean === null) {
                $annualDue = 0;
            } else {
                // Cast to string/float then to string with dot decimal
                $annualDue = (string) floatval($annualClean);
            }

            // Parse dates in dd/mm/YYYY (as in CSV) to Y-m-d H:i:s
            $createdAt = $this->parseDate($createdRaw);
            $updatedAt = $this->parseDate($updatedRaw);

            $data = [
                'pav_code' => $pavCode,
                'zones' => $zones,
                'property_type' => $propertyType,
                'annual_due' => $annualDue,
                'updated_at' => $updatedAt ?? Carbon::now(),
            ];

            // For insert, include created_at if present
            if ($createdAt) {
                $data['created_at'] = $createdAt;
            }

            // Use updateOrInsert to be idempotent by pav_code
            DB::table('property_assessment_values')->updateOrInsert(
                ['pav_code' => $pavCode],
                $data
            );
        }

        fclose($handle);
        $this->command->info('PAV data seed completed.');
    }

    /**
     * Try to parse various date formats used in the CSV. Returns Y-m-d H:i:s or null.
     *
     * @param string|null $value
     * @return string|null
     */
    protected function parseDate($value)
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);
        // Expected format in CSV: dd/mm/YYYY
        try {
            if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->startOfDay()->toDateTimeString();
            }

            // Try ISO formats
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Exception $e) {
            // If parsing fails, return null and let DB default handle it
            return null;
        }
    }
}


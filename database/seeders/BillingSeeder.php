<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recordsPerMonth = 10; // Adjust as needed
        $year = 2025;
        $randomRecords = [];
        for ($month = 1; $month <= 12; $month++) {
            for ($i = 0; $i < $recordsPerMonth; $i++) {
                $randomRecords[] = [
                    'assessed_value' => rand(1000, 10000),
                    'bill_amount' => rand(500, 5000),
                    'bill_rate' => rand(5, 20) / 100,
                    'paid_amount' => rand(0, 5000),
                    'assessment_no' => 'ASM-' . strtoupper(Str::random(8)),
                    'building_code' => 'B-' . strtoupper(Str::random(5)),
                    'entry_date' => Carbon::create($year, $month, rand(1, 28))->format('Y-m-d'),
                    'objection' => rand(0, 1),
                    'paid' => rand(0, 1) ,
                    'pav_code' => 'B1',
                    'year' => $year,
                    'lga_id' => rand(1, 22),
                    'billed_by' => rand(1, 2),
                    'property_id' => rand(1, 100),
                    'url' => substr(sha1(time()),29,40),
                ];
            }
        }

        // Insert the generated records into the database
        DB::table('billings')->insert($randomRecords);

        echo "Random records for the 12 months of $year have been inserted successfully!";

    }
}

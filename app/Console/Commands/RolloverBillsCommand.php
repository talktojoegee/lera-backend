<?php

namespace App\Console\Commands;

use App\Models\Billing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolloverBillsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rolloverBillsCommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */


    public function handle()
    {
        $year = 2025;
        $query = Billing::where('year', $year);
        $total = $query->count();
        $this->info("Rolling over 2025 bills to 2026...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $batchSize = 1000;

        $query->chunkById($batchSize, function ($bills) use ($bar) {
            $now = now();
            $rows = [];
            foreach ($bills as $bill) {
                $uniqueNumber = uniqid();
                $rows[] = [
                    'assessed_value' => $bill->assessed_value ?? 0,
                    'bill_amount'   => $bill->bill_amount,
                    'minimum_luc'   => $bill->minimum_luc,
                    'bill_rate'     => $bill->bill_rate,
                    'ba'            => $bill->ba,
                    'rr'            => $bill->rr,
                    'dr'            => $bill->dr,
                    'dr_value'      => $bill->dr_value,
                    'lr'            => $bill->lr,
                    'la'            => $bill->la,
                    'br'            => $bill->br,
                    'cr'            => $bill->cr,
                    'paid_amount'   => 0,

                    // 🔑 new unique assessment number
                    'assessment_no' => $uniqueNumber,

                    'building_code' => $bill->building_code,
                    'entry_date'    => now(),
                    'status'        => 3,
                    'date_returned' => now(),

                    'pav_code'      => $bill->pav_code,
                    'year'          => 2026,
                    'lga_id'        => $bill->lga_id,
                    'billed_by'     => $bill->billed_by,
                    'url'           => Str::random(12),
                    'property_id'   => $bill->property_id,
                    'zone_name'     => $bill->zone_name,
                    'ward'          => $bill->ward,

                    'date_authorized' => $now,
                    'date_actioned'   => $now,

                    'class_id'      => $bill->class_id,
                    'occupancy'     => $bill->occupancy,
                    'special'       => $bill->special,
                    'property_use'  => $bill->property_use,
                    'reviewed_by'   => $bill->reviewed_by,
                    'date_reviewed' => $bill->date_reviewed,
                    'property_name' => $bill->property_name,

                    'rollover'   => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $bar->advance();
            }
            DB::table('billings')->insert($rows);
        });
        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Billing rollover completed successfully.');

    }
}


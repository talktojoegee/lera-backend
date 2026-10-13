<?php

namespace App\Traits;

use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
use App\Support\PropertyTypeMapper;
use Illuminate\Support\Facades\Log;

/**
 * BillingProcessTrait
 *
 * Handles all billing calculations and processing logic
 * Can be used in jobs, commands, or controllers
 */
trait BillingProcessTrait
{
    /**
     * Process billing for properties
     *
     * @param  int  $lgaId  LGA ID (0 for all)
     * @param  int  $year  Billing year
     * @param  int  $billedBy  User ID of who billed
     * @param  callable|null  $callback  Optional progress callback
     * @return array Statistics array
     */
    public function processBilling($lgaId, $year, $billedBy, $callback = null)
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            // Get properties to bill
            $propertyLists = $this->_getPropertiesToBill($lgaId);
            $stats['total'] = count($propertyLists);

            Log::info('BillingProcessTrait: Starting billing process', [
                'lga_id' => $lgaId,
                'year' => $year,
                'property_count' => $stats['total'],
            ]);

            foreach ($propertyLists as $list) {
                try {
                    if ($this->_billExists($list->building_code, $year)) {
                        $stats['skipped']++;
                        if ($callback) {
                            call_user_func($callback, " Bill already exists for {$list->building_code}");
                        }

                        continue;
                    }

                    $pav = PropertyTypeMapper::findPavForProperty($list);

                    if ($pav === null) {
                        $stats['skipped']++;
                        $hint = PropertyTypeMapper::pavTableEmpty()
                            ? 'property_assessment_values table is empty — run php artisan pav:import on the server'
                            : 'no matching property_type in property_assessment_values — run php artisan properties:backfill-pav after pav:import';
                        Log::warning('BillingProcessTrait: no PAV match for property — bill skipped', [
                            'building_code' => $list->building_code,
                            'property_type' => $list->property_type,
                            'sync_word' => $list->sync_word,
                            'property_use' => $list->property_use,
                            'pav_code' => $list->pav_code,
                            'hint' => $hint,
                        ]);
                        if ($callback) {
                            call_user_func($callback, "Skipped {$list->building_code} — no PAV match ({$hint})");
                        }

                        continue;
                    }

                    $this->_createBillingRecord($list, $pav, $year, $billedBy);
                    $stats['created']++;

                    if ($callback) {
                        call_user_func($callback, "✓ Bill created for {$list->building_code}");
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('BillingProcessTrait: Error processing property', [
                        'building_code' => $list->building_code,
                        'message' => $e->getMessage(),
                    ]);
                    if ($callback) {
                        call_user_func($callback, "✗ Error processing {$list->building_code}: {$e->getMessage()}");
                    }
                }
            }

            Log::info('BillingProcessTrait: Billing process completed', $stats);

            return $stats;

        } catch (\Exception $e) {
            Log::error('BillingProcessTrait: Billing process failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $stats;
        }
    }

    /**
     * Get properties to bill
     *
     * @param  int  $lgaId  LGA ID (0 for all)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function _getPropertiesToBill($lgaId)
    {
        $query = PropertyList::orderBy('id', 'DESC');

        if ($lgaId > 0) {
            $query->where('lga_id', $lgaId);
        }

        return $query->get();
    }

    /**
     * Check if bill already exists
     *
     * @param  string  $buildingCode
     * @param  int  $year
     */
    private function _billExists($buildingCode, $year): bool
    {
        return Billing::where('year', $year)
            ->where('building_code', $buildingCode)
            ->exists();
    }

    /**
     * Create billing record with calculations
     *
     * @param  PropertyList  $property
     * @param  Depreciation  $depreciation
     * @param  ChargeRate  $chargeRate
     * @param  int  $year
     * @param  int  $billedBy
     */
    private function _createBillingRecord($property, ?PropertyAssessmentValue $pav, $year, $billedBy): Billing
    {
        // Create billing record
        $billing = new Billing;
        $billing->building_code = $property->building_code;
        $billing->assessment_no = uniqid();
        $billing->assessed_value = 0;
        $billing->bill_amount = $pav?->annual_due ?? 0;
        $billing->minimum_luc = MinimumLuc::first()->amount ?? 0;
        $billing->year = $year;
        $billing->entry_date = now();
        $billing->billed_by = $billedBy ?? 5;

        // Rate values
        $billing->rr = 0;
        $billing->lr = 0;
        $billing->ba = 0;
        $billing->br = 0;
        $billing->dr = 0;
        $billing->cr = 0;
        $billing->dr_value = 0;
        $billing->status = 3;

        // Status
        $billing->paid_amount = 0;
        $billing->objection = 0;

        // Property info
        $billing->lga_id = $property->lga_id;
        $billing->property_id = $property->id;
        $billing->bill_rate = 0;
        $billing->pav_code = $pav?->pav_code;
        $billing->zone_name = $property->sub_zone ?? '';
        $billing->url = uniqid().mt_rand(1000, 9999);
        $billing->class_id = $property->class_id;
        $billing->property_use = $property->property_use;
        $billing->occupancy = $property->cr;
        $billing->la = 0;
        $billing->ward = $property->ward ?? '';
        $billing->street_name = $property->street_name ?? '';
        $billing->property_name = $property->property_name ?? '';
        $billing->save();

        return $billing;
    }
}

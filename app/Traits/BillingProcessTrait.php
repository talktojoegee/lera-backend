<?php

namespace App\Traits;

use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
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
     * @param int $lgaId LGA ID (0 for all)
     * @param int $year Billing year
     * @param int $billedBy User ID of who billed
     * @param callable|null $callback Optional progress callback
     * @return array Statistics array
     */
    public function processBilling($lgaId, $year, $billedBy, $callback = null)
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        try {
            // Get properties to bill
            $propertyLists = $this->_getPropertiesToBill($lgaId);
            $stats['total'] = count($propertyLists);

            Log::info('BillingProcessTrait: Starting billing process', [
                'lga_id' => $lgaId,
                'year' => $year,
                'property_count' => $stats['total']
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

                    $pav = PropertyAssessmentValue::where("property_type", $list->property_type)->first();

                    $this->_createBillingRecord($list, $pav, $year, $billedBy);
                    $stats['created']++;

                    if ($callback) {
                        call_user_func($callback, "✓ Bill created for {$list->building_code}");
                    }

                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('BillingProcessTrait: Error processing property', [
                        'building_code' => $list->building_code,
                        'message' => $e->getMessage()
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
                'trace' => $e->getTraceAsString()
            ]);
            return $stats;
        }
    }

    /**
     * Get properties to bill
     *
     * @param int $lgaId LGA ID (0 for all)
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
     * @param string $buildingCode
     * @param int $year
     * @return bool
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
     * @param PropertyList $property
     * @param PropertyAssessmentValue $pav
     * @param Depreciation $depreciation
     * @param ChargeRate $chargeRate
     * @param int $year
     * @param int $billedBy
     * @return Billing
     */
    private function _createBillingRecord($property, $pav, $year, $billedBy): Billing
    {
        // Create billing record
        $billing = new Billing();
        $billing->building_code = $property->building_code;
        $billing->assessment_no = uniqid();
        $billing->assessed_value = 0;
        $billing->bill_amount = $pav->annual_due ?? 0;
        $billing->minimum_luc = MinimumLuc::first()->amount ?? 0;
        $billing->year = $year;
        $billing->entry_date = now();
        $billing->billed_by = $billedBy ?? 5;

        // Rate values
        $billing->rr =  0;
        $billing->lr = 0;
        $billing->ba = 0;
        $billing->br = 0;
        $billing->dr = 0;
        $billing->cr = 0;
        $billing->dr_value = 0;

        // Status
        $billing->paid_amount = 0;
        $billing->objection = 0;

        // Property info
        $billing->lga_id = $property->lga_id;
        $billing->property_id = $property->id;
        $billing->bill_rate = 0;
        $billing->pav_code = $pav->pav_code;
        $billing->zone_name = $property->sub_zone ?? '';
        $billing->url = uniqid() . mt_rand(1000, 9999);
        $billing->class_id = $property->class_id;
        $billing->property_use = $property->property_use;
        $billing->occupancy = $property->cr;
        $billing->la = 0;
        $billing->ward = $property->ward ?? '';
        $billing->property_name = $property->property_name ?? '';
        $billing->save();
        return $billing;
    }
}


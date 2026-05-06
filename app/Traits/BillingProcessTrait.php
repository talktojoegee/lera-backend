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
                    // Check if bill already exists
                    if ($this->_billExists($list->building_code, $year)) {
                        $stats['skipped']++;
                        if ($callback) {
                            call_user_func($callback, "⊘ Bill already exists for {$list->building_code}");
                        }
                        continue;
                    }

                    // Get required related data
                    $pav = PropertyAssessmentValue::where("pav_code", $list->pav_code)->first();
                    $depreciation = Depreciation::find($list->dep_id);
                    $chargeRate = ChargeRate::find($list->cr);

                    // Validate required data exists
                    if (!$pav || !$depreciation || !$chargeRate) {
                        $stats['skipped']++;
                        if ($callback) {
                            call_user_func($callback, "⊘ Missing data for {$list->building_code}");
                        }
                        continue;
                    }

                    // Skip exempted properties (rate = 0)
                    if ($chargeRate->rate <= 0) {
                        $stats['skipped']++;
                        if ($callback) {
                            call_user_func($callback, "⊘ Exempted property {$list->building_code}");
                        }
                        continue;
                    }

                    // Create billing record
                    $this->_createBillingRecord($list, $pav, $depreciation, $chargeRate, $year, $billedBy);
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
    private function _createBillingRecord($property, $pav, $depreciation, $chargeRate, $year, $billedBy): Billing
    {
        // Calculate billing amounts
        $calculations = $this->_calculateBillingAmounts($property, $pav, $depreciation, $chargeRate);

        // Create billing record
        $billing = new Billing();
        $billing->building_code = $property->building_code;
        $billing->assessment_no = uniqid();
        $billing->assessed_value = $calculations['assessed_value'];
        $billing->bill_amount = $calculations['bill_amount'];
        $billing->minimum_luc = $calculations['minimum_luc'];
        $billing->year = $year;
        $billing->entry_date = now();
        $billing->billed_by = $billedBy ?? 5;

        // Rate values
        $billing->rr = $pav->rr ?? 0;
        $billing->lr = $pav->lr ?? 0;
        $billing->ba = $calculations['ba'];
        $billing->br = $pav->br ?? 0;
        $billing->dr = $depreciation->depreciation_rate ?? 0;
        $billing->cr = $chargeRate->rate;
        $billing->dr_value = $depreciation->depreciation_rate ?? 0;

        // Status
        $billing->paid_amount = 0;
        $billing->objection = 0;

        // Property info
        $billing->lga_id = $property->lga_id;
        $billing->property_id = $property->id;
        $billing->bill_rate = $pav->value_rate ?? 0;
        $billing->pav_code = $pav->pav_code;
        $billing->zone_name = $property->sub_zone ?? '';
        $billing->url = uniqid() . mt_rand(1000, 9999);
        $billing->class_id = $property->class_id;
        $billing->property_use = $property->property_use;
        $billing->occupancy = $property->cr;
        $billing->la = $calculations['la'];
        $billing->ward = $property->ward ?? '';
        $billing->property_name = $property->property_name ?? '';

        $billing->save();
        return $billing;
    }

    /**
     * Calculate all billing amounts
     *
     * Formula: ((LA * LR) + (BA * BR * DR)) * RR * CR
     * Where:
     * - LA = Land Area
     * - LR = Land Rate
     * - BA = Building Area
     * - BR = Building Rate
     * - DR = Depreciation Rate
     * - RR = Revenue Rate
     * - CR = Charge Rate
     *
     * @param PropertyList $property
     * @param PropertyAssessmentValue $pav
     * @param Depreciation $depreciation
     * @param ChargeRate $chargeRate
     * @return array
     */
    private function _calculateBillingAmounts($property, $pav, $depreciation, $chargeRate): array
    {
        // Extract values
        $la = (int)($property->area ?? 1);
        $lr = $pav->lr ?? 1;
        $ba = $property->ba ?? 0;
        $br = $pav->br ?? 0;
        $dr = ($depreciation->value ?? 0) * 0.01;
        $rr = (100 - ($pav->rr ?? 0)) * 0.01;
        $cr = ($chargeRate->rate ?? 0) * 0.01;

        // Calculate assessed value (before charge rate)
        $assessed_value = (($la * $lr) + ($ba * $br * $dr)) * $rr;

        // Calculate LUC (with charge rate)
        $luc = $assessed_value * $cr;
        $billAmount = ceil($luc);

        // Get minimum LUC
        $minimumLuc = MinimumLuc::first();
        $minimumAmount = $minimumLuc->amount ?? 0;

        // Determine final bill amount and minimum LUC adjustment
        $finalBillAmount = ($billAmount > $minimumAmount) ? $billAmount : $minimumAmount;
        $minimumLucAdjustment = ($billAmount < $minimumAmount) ? $billAmount : 0;

        Log::debug('BillingProcessTrait: Calculation details', [
            'la' => $la,
            'lr' => $lr,
            'ba' => $ba,
            'br' => $br,
            'dr' => $dr,
            'rr' => $rr,
            'cr' => $cr,
            'assessed_value' => $assessed_value,
            'luc' => $luc,
            'bill_amount' => $billAmount,
            'minimum_luc' => $minimumAmount,
            'final_bill_amount' => $finalBillAmount
        ]);

        return [
            'la' => $la,
            'ba' => $ba,
            'assessed_value' => $assessed_value,
            'bill_amount' => $finalBillAmount,
            'minimum_luc' => $minimumLucAdjustment
        ];
    }
}


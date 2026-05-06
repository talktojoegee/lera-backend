<?php

namespace App\Traits;

use App\Models\ChargeRate;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PropertySyncTrait
 *
 * Handles synchronization of property data from remote enumeration tables
 * to local property_lists table. Can process single or multiple property IDs.
 */
trait PropertySyncTrait
{
    use UtilityTrait;
    protected $syncCounter = 0;
    protected $exceptionCounter = 0;
    protected $processedCounter = 0;

    /**
     * Synchronize property data for given property ID(s)
     *
     * @param int|string|array $propertyIds Single property ID or array of property IDs
     * @param callable|null $outputCallback Optional callback for console output (e.g., $this->info())
     * @return array Returns ['synced' => count, 'exceptions' => count, 'total' => count]
     */
    public function syncPropertyData($propertyIds, $outputCallback = null)
    {
        $this->resetCounters();

        // Normalize input to array
        $propertyIds = is_array($propertyIds) ? $propertyIds : ($propertyIds ? [$propertyIds] : []);

        try {
            // Query remote enumeration table for given property IDs
            $query = DB::connection('pgsql')
                ->table('lekki_enumeration')
                ->where('completeness_status', 'Complete');

            // Only apply whereIn filter if property IDs are provided
            if (!empty($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }

            $query->orderBy('id', 'DESC');
            $records = $query->cursor();

            foreach ($records as $record) {
                $this->processedCounter++;

                try {
                    // Log progress
                    Log::info('PropertySyncTrait: processing record', [
                        'counter' => $this->processedCounter,
                        'record_id' => $record->id,
                        'property_id' => $record->property_id
                    ]);

                    // Output progress if callback provided
                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "[{$this->processedCounter}] Processing {$record->property_id}");
                    }

                    // Process the record
                    $this->_processPropertyRecord($record, $outputCallback);

                } catch (\Exception $ex) {
                    $this->exceptionCounter++;
                    Log::error("PropertySyncTrait: EXCEPTION processing record {$record->id}", [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                        'record_id' => $record->id,
                        'property_id' => $record->property_id
                    ]);

                    // Show error in output
                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "❌ ERROR [{$record->property_id}]: {$ex->getMessage()}");
                    }

                    // Mark as exception in remote table
                    try {
                        DB::connection('pgsql')
                            ->table('lekki_enumeration')
                            ->where('id', $record->id)
                            ->update(['bill_sync' => 2]);
                    } catch (\Exception $updateEx) {
                        Log::error('PropertySyncTrait: failed to mark record as exception', [
                            'message' => $updateEx->getMessage()
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('PropertySyncTrait failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return [
            'synced' => $this->syncCounter,
            'exceptions' => $this->exceptionCounter,
            'total' => $this->processedCounter
        ];
    }

    /**
     * Process a single property record from enumeration table
     *
     * @param object $record The enumeration record
     * @param callable|null $outputCallback Optional callback for console output
     * @return void
     */
    private function _processPropertyRecord($record, $outputCallback = null)
    {
        $propertyList = PropertyList::where("building_code", $record->property_id)->first();

        if (!$propertyList) {
            // Build data array for new property
            $propertyData = $this->_buildPropertyData($record);

            try {
                // Log the data being inserted for debugging
                Log::debug('PropertySyncTrait: attempting insert with data', [
                    'building_code' => $propertyData['building_code'] ?? 'MISSING',
                    'property_type' => $propertyData['property_type'] ?? 'MISSING',
                    'data_count' => count($propertyData)
                ]);

                // Try creating via model
                $new = PropertyList::create($propertyData);

                if (!empty($new->id)) {
                    Log::info('PropertySyncTrait: created PropertyList via model', [
                        'id' => $new->id,
                        'building_code' => $new->building_code
                    ]);

                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "✓ Created PropertyList id={$new->id}");
                    }

                    $this->syncCounter++;
                } else {
                    // Fallback: Use raw insert
                    Log::warning('PropertySyncTrait: model create returned no ID, using fallback insert', [
                        'building_code' => $record->property_id
                    ]);

                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "⚠️ Model create failed, attempting fallback insert for {$record->property_id}");
                    }

                    try {
                        $insertedId = DB::connection(config('database.default'))
                            ->table('property_lists')
                            ->insertGetId($propertyData);

                        Log::info('PropertySyncTrait: fallback insert successful', [
                            'id' => $insertedId,
                            'building_code' => $record->property_id
                        ]);

                        if ($outputCallback && is_callable($outputCallback)) {
                            call_user_func($outputCallback, "✓ Fallback insert id={$insertedId}");
                        }

                        $this->syncCounter++;
                    } catch (\Exception $fallbackEx) {
                        Log::error('PropertySyncTrait: fallback insert also failed', [
                            'message' => $fallbackEx->getMessage(),
                            'trace' => $fallbackEx->getTraceAsString(),
                            'building_code' => $record->property_id
                        ]);

                        if ($outputCallback && is_callable($outputCallback)) {
                            call_user_func($outputCallback, "✗ Both model and raw insert failed: {$fallbackEx->getMessage()}");
                        }

                        throw $fallbackEx;
                    }
                }

            } catch (\Exception $fbEx) {
                Log::error('PropertySyncTrait: insert operation failed completely', [
                    'message' => $fbEx->getMessage(),
                    'trace' => $fbEx->getTraceAsString(),
                    'property_id' => $record->property_id
                ]);

                if ($outputCallback && is_callable($outputCallback)) {
                    call_user_func($outputCallback, "✗ FAILED to insert: {$fbEx->getMessage()}");
                }

                throw $fbEx;
            }
        } else {
            // Property already exists - just update sync status
            if ($outputCallback && is_callable($outputCallback)) {
                call_user_func($outputCallback, "ⓘ Property already exists: {$record->property_id}");
            }
        }

        // Mark record as synced in remote table
        try {
            DB::connection('pgsql')
                ->table('lekki_enumeration')
                ->where('id', $record->id)
                ->update(['bill_sync' => 1]);
        } catch (\Exception $updateEx) {
            Log::error('PropertySyncTrait: failed to mark record as synced', [
                'message' => $updateEx->getMessage(),
                'record_id' => $record->id
            ]);
        }
    }

    /**
     * Build property data array for insertion
     *
     * @param object $record The enumeration record
     * @return array Property data ready for insertion
     */
    private function _buildPropertyData($record): array
    {
        try {
            $chargeRate = $this->_getChargeRate($record->business_name_1, $record->property_type) ?: ChargeRate::first();
            $areaVal = $this->convertToSqm('2000_Acre');

            // Determine property classification
            $propertyClassification = ($chargeRate && $chargeRate->id == 7)
                ? PropertyClassification::find(2)
                : PropertyClassification::find(1);

            // Detect landuse/class id
            $landuse = $this->_isCommercial($record->business_name_1, $record->property_type);

            // Sync word is the property type string
            $syncWord = $record->property_type ?? 'Residential';

            // Get PAV record
            $pavRecord = $this->_getPavCode($record->property_type);

            Log::debug('PropertySyncTrait: building property data', [
                'property_id' => $record->property_id,
                'chargeRate' => $chargeRate?->id,
                'propertyClassification' => $propertyClassification?->id,
                'pavRecord' => $pavRecord?->pav_code,
                'syncWord' => $syncWord
            ]);

            $data = [
                'address' => ($record->confirmed_house_number ?? '') . ' ' . ($record->street_name ?? ''),
                'area' => !empty($areaVal) ? str_replace("_sqm", "", $areaVal) : 0,
                'building_code' => $record->property_id,
                'image' => $record->photo_front ?? null,
                'owner_email' => $record->owner_email ?? null,
                'owner_gsm' => $record->owner_phone ?? null,
                'owner_name' => $record->owner_name ?? null,
                'pav_code' => $pavRecord->pav_code ?? null,
                'power' => 0,
                'storey' => '',
                'water' => 0,
                'zone_name' => $pavRecord->zones ?? 'A',
                'sub_zone' => $pavRecord->zones ?? 'A1',
                'ward' => $pavRecord->zones ?? 'A1',
                'occupant' => $record->owner_name ?? null,
                'building_age' => 0,
                'pay_status' => null,
                'lga_id' => 1,
                'class_name' => $propertyClassification->class_name ?? '',
                'class_id' => 1,
                'sync_word' => $syncWord,
                'property_use' => $syncWord,
                'cr' => $chargeRate->id ?? 1,
                'actual_age' => 0,
                'longitude' => $record->longitude ?? null,
                'latitude' => $record->latitude ?? null,
                'property_name' => $record->business_name_1 ?? 'N/A',
                'occupier' => $record->owner_name ?? null,
                'property_address' => ($record->confirmed_house_number ?? '') . ' ' . ($record->street_name ?? ''),
                'dep_id' => 1,
                'ba' => $record->area_from_bfp ?? 200,
                'property_type' => $record->property_type ?? 'Residential',
                'business_type' => $record->business_type ?? 'Private',
                'no_of_floors' => $record->no_of_floors ?? 1,
                'no_of_units' => $record->no_of_units ?? 1,
                'revenue_category' => $record->revenue_category ?? 'Residential',
                'photo_gate' => $record->photo_gate ?? '',
                'photo_signage' => $record->photo_signage ?? '',
                'photo_street' => $record->photo_street ?? '',
                'area_in_me' => $record->area_in_me ?? '',
                'confidence' => $record->confidence ?? '',
                'full_plus_' => $record->full_plus_ ?? '',
                'contact_name' => $record->contact_name ?? '',
                'contact_phone' => $record->contact_phone ?? '',
                'contact_address' => $record->contact_address ?? '',
                'contact_email' => $record->contact_email ?? '',
                'confirmed_house_number' => $record->confirmed_house_number ?? '',
                'assigned_house_number' => $record->assigned_house_number ?? '',
                'business_name_1' => $record->business_name_1 ?? '',
                'business_name_2' => $record->business_name_2 ?? '',
                'business_name_3' => $record->business_name_3 ?? '',
                'business_name_4' => $record->business_name_4 ?? '',
            ];

            return $data;
        } catch (\Exception $e) {
            Log::error('PropertySyncTrait: error building property data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'property_id' => $record->property_id ?? 'UNKNOWN'
            ]);
            throw $e;
        }
    }

    /**
     * Get charge rate based on property type/business name
     *
     * @param string|null $businessName
     * @param string|null $propertyType
     * @return ChargeRate|null
     */
    private function _getChargeRate($businessName = null, $propertyType = null)
    {
        if (
            (!empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (!empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return ChargeRate::find(7);
        }

        return ChargeRate::first();
    }

    /**
     * Determine if property is commercial
     *
     * @param string|null $businessName
     * @param string|null $propertyType
     * @return int Class ID (1 = Residential, 2 = Commercial)
     */
    private function _isCommercial($businessName = null, $propertyType = null): int
    {
        if (
            (!empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (!empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return 2;
        }

        return 1;
    }

    /**
     * Get PAV code record
     *
     * @param int $classId Property classification ID
     * @param string $zone Zone character
     * @param string|null $syncWord Property type sync word
     * @return PropertyAssessmentValue|null
     */
    private function _getPavCode($propertyType)
    {
        return PropertyAssessmentValue::where("property_type", $propertyType)
            ->first();
    }

    /**
     * Reset all counters
     */
    private function resetCounters(): void
    {
        $this->syncCounter = 0;
        $this->exceptionCounter = 0;
        $this->processedCounter = 0;
    }

    /**
     * Get current sync statistics
     *
     * @return array Statistics array
     */
    public function getSyncStats(): array
    {
        return [
            'synced' => $this->syncCounter,
            'exceptions' => $this->exceptionCounter,
            'total' => $this->processedCounter
        ];
    }
}


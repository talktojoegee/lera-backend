<?php

namespace App\Traits;

use App\Models\Billing;
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

    protected $updatedCounter = 0;

    protected $skippedCounter = 0;

    protected $unchangedCounter = 0;

    protected $billingsUpdatedCounter = 0;

    protected $updateRemoteSync = false;  // Set to true if remote table has bill_sync column

    /**
     * Synchronize property data for given property ID(s)
     *
     * @param  int|string|array  $propertyIds  Single property ID or array of property IDs
     * @param  callable|null  $outputCallback  Optional callback for console output (e.g., $this->info())
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
            if (! empty($propertyIds)) {
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
                        'property_id' => $record->property_id,
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
                        'property_id' => $record->property_id,
                    ]);

                    // Show error in output
                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "❌ ERROR [{$record->property_id}]: {$ex->getMessage()}");
                    }

                    // Mark as exception in remote table
                    try {
                        if ($this->updateRemoteSync) {
                            DB::connection('pgsql')
                                ->table('lekki_enumeration')
                                ->where('id', $record->id)
                                ->update(['bill_sync' => 2]);
                        }
                    } catch (\Exception $updateEx) {
                        Log::error('PropertySyncTrait: failed to mark record as exception', [
                            'message' => $updateEx->getMessage(),
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('PropertySyncTrait failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [
            'synced' => $this->syncCounter,
            'exceptions' => $this->exceptionCounter,
            'total' => $this->processedCounter,
        ];
    }

    /**
     * Update street_name (and composed address fields) on existing property_lists rows
     * from remote lekki_enumeration records.
     *
     * @param  int|string|array  $propertyIds
     * @param  callable|null  $outputCallback
     * @return array{total: int, updated: int, unchanged: int, skipped: int, exceptions: int}
     */
    public function syncPropertyStreetNames($propertyIds, $outputCallback = null, bool $dryRun = false): array
    {
        $this->resetCounters();

        $propertyIds = is_array($propertyIds) ? $propertyIds : ($propertyIds ? [$propertyIds] : []);

        try {
            $query = DB::connection('pgsql')
                ->table('lekki_enumeration')
                ->where('completeness_status', 'Complete');

            if (! empty($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }

            foreach ($query->orderBy('id', 'DESC')->cursor() as $record) {
                $this->processedCounter++;

                try {
                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "[{$this->processedCounter}] Processing {$record->property_id}");
                    }

                    $this->_updatePropertyStreetNameFromRecord($record, $outputCallback, $dryRun);
                } catch (\Exception $ex) {
                    $this->exceptionCounter++;
                    Log::error('PropertySyncTrait: street_name update failed', [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                        'record_id' => $record->id ?? null,
                        'property_id' => $record->property_id ?? null,
                    ]);

                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "ERROR [{$record->property_id}]: {$ex->getMessage()}");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('PropertySyncTrait: syncPropertyStreetNames failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        $backfilled = $this->backfillBillingsStreetNamesFromPropertyLists($dryRun, $outputCallback);
        $this->billingsUpdatedCounter += $backfilled;

        return $this->getStreetNameSyncStats();
    }

    /**
     * @param  callable|null  $outputCallback
     */
    private function _updatePropertyStreetNameFromRecord(object $record, $outputCallback = null, bool $dryRun = false): void
    {
        $propertyList = PropertyList::where('building_code', $record->property_id)->first();
        if ($propertyList === null) {
            $this->skippedCounter++;

            if ($outputCallback && is_callable($outputCallback)) {
                call_user_func($outputCallback, "Skipped — no local property for {$record->property_id}");
            }

            return;
        }

        $streetName = trim((string) ($record->street_name ?? ''));
        if ($streetName === '') {
            $streetName = trim((string) ($propertyList->street_name ?? ''));
        }

        $composedAddress = $this->composeAddressFromEnumeration($record);
        $updates = [];

        if ($streetName !== '' && (string) ($propertyList->street_name ?? '') !== $streetName) {
            $updates['street_name'] = $streetName;
        }

        if ($composedAddress !== '') {
            if ((string) ($propertyList->address ?? '') !== $composedAddress) {
                $updates['address'] = $composedAddress;
            }
            if ((string) ($propertyList->property_address ?? '') !== $composedAddress) {
                $updates['property_address'] = $composedAddress;
            }
        }

        if ($updates === []) {
            $this->unchangedCounter++;
            $billingCount = $this->applyStreetNameToBillings(
                $record->property_id,
                $streetName,
                (int) $propertyList->id,
                $dryRun
            );
            $this->billingsUpdatedCounter += $billingCount;

            if ($outputCallback && is_callable($outputCallback)) {
                $message = $billingCount > 0
                    ? "Property unchanged, {$billingCount} bill(s) synced for {$record->property_id}"
                    : "Unchanged: {$record->property_id}";
                call_user_func($outputCallback, $message);
            }

            return;
        }

        if ($dryRun) {
            $this->updatedCounter++;
            $billingCount = $this->countBillingsNeedingStreetName(
                $record->property_id,
                $streetName,
                (int) $propertyList->id
            );

            if ($outputCallback && is_callable($outputCallback)) {
                call_user_func(
                    $outputCallback,
                    "Dry run — would update {$record->property_id}: ".json_encode($updates)." ({$billingCount} bill(s))"
                );
            }

            return;
        }

        $propertyList->update($updates);
        $this->updatedCounter++;

        $billingCount = $this->applyStreetNameToBillings(
            $record->property_id,
            $streetName,
            (int) $propertyList->id,
            false
        );
        $this->billingsUpdatedCounter += $billingCount;

        Log::info('PropertySyncTrait: updated street_name', [
            'property_id' => $record->property_id,
            'property_list_id' => $propertyList->id,
            'updates' => $updates,
            'billings_updated' => $billingCount,
        ]);

        if ($outputCallback && is_callable($outputCallback)) {
            call_user_func(
                $outputCallback,
                "Updated {$record->property_id}: ".json_encode($updates)." ({$billingCount} bill(s))"
            );
        }
    }

    protected function applyStreetNameToBillings(
        string $buildingCode,
        string $streetName,
        ?int $propertyListId = null,
        bool $dryRun = false,
    ): int {
        if ($dryRun) {
            return $this->countBillingsNeedingStreetName($buildingCode, $streetName, $propertyListId);
        }

        return $this->syncStreetNameToBillings($buildingCode, $streetName, $propertyListId);
    }

    protected function countBillingsNeedingStreetName(
        string $buildingCode,
        string $streetName,
        ?int $propertyListId = null,
    ): int {
        $streetName = trim($streetName);
        if ($streetName === '') {
            return 0;
        }

        return $this->billingsNeedingStreetNameQuery($buildingCode, $streetName, $propertyListId)->count();
    }

    protected function syncStreetNameToBillings(
        string $buildingCode,
        string $streetName,
        ?int $propertyListId = null,
    ): int {
        $streetName = trim($streetName);
        if ($streetName === '') {
            return 0;
        }

        return $this->billingsNeedingStreetNameQuery($buildingCode, $streetName, $propertyListId)
            ->update(['street_name' => $streetName]);
    }

    protected function billingsNeedingStreetNameQuery(
        string $buildingCode,
        string $streetName,
        ?int $propertyListId = null,
    ) {
        $buildingCode = trim($buildingCode);

        return Billing::query()
            ->where(function ($query) use ($buildingCode, $propertyListId) {
                if ($buildingCode !== '') {
                    $query->where('building_code', $buildingCode);
                }
                if ($propertyListId !== null && $propertyListId > 0) {
                    if ($buildingCode !== '') {
                        $query->orWhere('property_id', $propertyListId);
                    } else {
                        $query->where('property_id', $propertyListId);
                    }
                }
            })
            ->where(function ($query) use ($streetName) {
                $query->whereNull('street_name')
                    ->orWhere('street_name', '!=', $streetName);
            });
    }

    /**
     * Copy street_name from property_lists onto billings that are still blank or stale.
     */
    public function backfillBillingsStreetNamesFromPropertyLists(
        bool $dryRun = false,
        $outputCallback = null,
    ): int {
        $rows = DB::table('billings')
            ->join('property_lists', 'billings.property_id', '=', 'property_lists.id')
            ->whereNotNull('property_lists.street_name')
            ->where('property_lists.street_name', '!=', '')
            ->where(function ($query) {
                $query->whereNull('billings.street_name')
                    ->orWhereColumn('billings.street_name', '!=', 'property_lists.street_name');
            })
            ->select('billings.id', 'property_lists.street_name')
            ->get();

        $updated = 0;

        foreach ($rows as $row) {
            if (! $dryRun) {
                Billing::where('id', $row->id)->update(['street_name' => $row->street_name]);
            }
            $updated++;
        }

        if ($updated > 0 && $outputCallback && is_callable($outputCallback)) {
            call_user_func(
                $outputCallback,
                ($dryRun ? 'Dry run — would backfill ' : 'Backfilled ')."{$updated} billing row(s) from property_lists"
            );
        }

        return $updated;
    }

    protected function composeAddressFromEnumeration(object $record): string
    {
        return trim(trim((string) ($record->confirmed_house_number ?? '')).' '.trim((string) ($record->street_name ?? '')));
    }

    /**
     * @return array{total: int, updated: int, unchanged: int, skipped: int, exceptions: int}
     */
    public function getStreetNameSyncStats(): array
    {
        return [
            'total' => $this->processedCounter,
            'updated' => $this->updatedCounter,
            'unchanged' => $this->unchangedCounter,
            'skipped' => $this->skippedCounter,
            'billings_updated' => $this->billingsUpdatedCounter,
            'exceptions' => $this->exceptionCounter,
        ];
    }

    /**
     * Process a single property record from enumeration table
     *
     * @param  object  $record  The enumeration record
     * @param  callable|null  $outputCallback  Optional callback for console output
     * @return void
     */
    private function _processPropertyRecord($record, $outputCallback = null)
    {
        $propertyList = PropertyList::where('building_code', $record->property_id)->first();

        if (! $propertyList) {
            // Build data array for new property
            $propertyData = $this->_buildPropertyData($record);

            try {
                // Log the data being inserted for debugging
                Log::debug('PropertySyncTrait: attempting insert with data', [
                    'building_code' => $propertyData['building_code'] ?? 'MISSING',
                    'property_type' => $propertyData['property_type'] ?? 'MISSING',
                    'data_count' => count($propertyData),
                ]);

                // Try creating via model
                $new = PropertyList::create($propertyData);

                if (! empty($new->id)) {
                    Log::info('PropertySyncTrait: created PropertyList via model', [
                        'id' => $new->id,
                        'building_code' => $new->building_code,
                    ]);

                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "✓ Created PropertyList id={$new->id}");
                    }

                    if ($streetName = trim((string) ($propertyData['street_name'] ?? ''))) {
                        $this->syncStreetNameToBillings($record->property_id, $streetName, (int) $new->id);
                    }

                    $this->syncCounter++;
                } else {
                    // Fallback: Use raw insert
                    Log::warning('PropertySyncTrait: model create returned no ID, using fallback insert', [
                        'building_code' => $record->property_id,
                    ]);

                    if ($outputCallback && is_callable($outputCallback)) {
                        call_user_func($outputCallback, "Model create failed, attempting fallback insert for {$record->property_id}");
                    }

                    try {
                        $insertedId = DB::connection(config('database.default'))
                            ->table('property_lists')
                            ->insertGetId($propertyData);

                        Log::info('PropertySyncTrait: fallback insert successful', [
                            'id' => $insertedId,
                            'building_code' => $record->property_id,
                        ]);

                        if ($outputCallback && is_callable($outputCallback)) {
                            call_user_func($outputCallback, "✓ Fallback insert id={$insertedId}");
                        }

                        if ($streetName = trim((string) ($propertyData['street_name'] ?? ''))) {
                            $this->syncStreetNameToBillings($record->property_id, $streetName, (int) $insertedId);
                        }

                        $this->syncCounter++;
                    } catch (\Exception $fallbackEx) {
                        Log::error('PropertySyncTrait: fallback insert also failed', [
                            'message' => $fallbackEx->getMessage(),
                            'trace' => $fallbackEx->getTraceAsString(),
                            'building_code' => $record->property_id,
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
                    'property_id' => $record->property_id,
                ]);

                if ($outputCallback && is_callable($outputCallback)) {
                    call_user_func($outputCallback, "✗ FAILED to insert: {$fbEx->getMessage()}");
                }

                throw $fbEx;
            }
        } else {
            $streetName = trim((string) ($record->street_name ?? ''));
            $composedAddress = $this->composeAddressFromEnumeration($record);
            $updates = array_filter([
                'street_name' => $streetName !== '' ? $streetName : null,
                'confirmed_house_number' => $record->confirmed_house_number ?? null,
                'address' => $composedAddress !== '' ? $composedAddress : null,
                'property_address' => $composedAddress !== '' ? $composedAddress : null,
            ], fn ($value) => $value !== null && $value !== '');

            if ($updates !== []) {
                $propertyList->update($updates);
            }

            if ($streetName === '') {
                $streetName = trim((string) ($propertyList->fresh()->street_name ?? ''));
            }

            if ($streetName !== '') {
                $this->syncStreetNameToBillings($record->property_id, $streetName, (int) $propertyList->id);
            }

            if ($outputCallback && is_callable($outputCallback)) {
                call_user_func($outputCallback, "Property updated: {$record->property_id}");
            }
        }

        // Mark record as synced in remote table (only if enabled)
        if ($this->updateRemoteSync) {
            try {
                DB::connection('pgsql')
                    ->table('lekki_enumeration')
                    ->where('id', $record->id)
                    ->update(['bill_sync' => 1]);
            } catch (\Exception $updateEx) {
                Log::error('PropertySyncTrait: failed to mark record as synced', [
                    'message' => $updateEx->getMessage(),
                    'record_id' => $record->id,
                ]);
            }
        }
    }

    /**
     * Build property data array for insertion
     *
     * @param  object  $record  The enumeration record
     * @return array Property data ready for insertion
     */
    private function _buildPropertyData($record): array
    {
        try {
            $chargeRate = $this->_getChargeRate($record->business_name_1, $record->property_type) ?: ChargeRate::first();
            $areaVal = $this->convertToSqm('2000_Acre');
            $propertyClassification = ($chargeRate && $chargeRate->id == 7)
                ? PropertyClassification::find(2)
                : PropertyClassification::find(1);

            // Detect landuse/class id
            $landuse = $this->_isCommercial($record->business_name_1, $record->property_type);

            // Sync word is the property type string
            $syncWord = $record->property_type ?? 'Residential';

            // Get PAV record - using property_type directly from AssessmentValue
            $pavRecord = PropertyAssessmentValue::where('property_type', $syncWord)->first();

            Log::debug('PropertySyncTrait: building property data', [
                'property_id' => $record->property_id,
                'chargeRate' => $chargeRate?->id,
                'propertyClassification' => $propertyClassification?->id,
                'pavRecord' => $pavRecord?->pav_code,
                'syncWord' => $syncWord,
            ]);

            $data = [
                'address' => ($record->confirmed_house_number ?? '').' '.($record->street_name ?? ''),
                'area' => ! empty($areaVal) ? str_replace('_sqm', '', $areaVal) : 0,
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
                'property_address' => ($record->confirmed_house_number ?? '').' '.($record->street_name ?? ''),
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
                'street_name' => $record->street_name ?? '',
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
                'property_id' => $record->property_id ?? 'UNKNOWN',
            ]);
            throw $e;
        }
    }

    /**
     * Get charge rate based on property type/business name
     *
     * @param  string|null  $businessName
     * @param  string|null  $propertyType
     * @return ChargeRate|null
     */
    private function _getChargeRate($businessName = null, $propertyType = null)
    {
        if (
            (! empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (! empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return ChargeRate::find(7);
        }

        return ChargeRate::first();
    }

    /**
     * Determine if property is commercial
     *
     * @param  string|null  $businessName
     * @param  string|null  $propertyType
     * @return int Class ID (1 = Residential, 2 = Commercial)
     */
    private function _isCommercial($businessName = null, $propertyType = null): int
    {
        if (
            (! empty($propertyType) && str_contains(strtolower($propertyType), 'commercial')) ||
            (! empty($businessName) && str_contains(strtolower($businessName), 'commercial'))
        ) {
            return 2;
        }

        return 1;
    }

    /**
     * Reset all counters
     */
    private function resetCounters(): void
    {
        $this->syncCounter = 0;
        $this->exceptionCounter = 0;
        $this->processedCounter = 0;
        $this->updatedCounter = 0;
        $this->skippedCounter = 0;
        $this->unchangedCounter = 0;
        $this->billingsUpdatedCounter = 0;
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
            'total' => $this->processedCounter,
        ];
    }

    /**
     * Force synchronize an existing property from remote database
     *
     * @param  string  $buildingCode  The building code/property ID
     * @return array Result array with success/error info
     */
    public function updatePropertyFromRemote($buildingCode): array
    {
        try {
            $propertyDetail = PropertyList::where('building_code', $buildingCode)->first();
            if (empty($propertyDetail)) {
                return [
                    'success' => false,
                    'message' => 'Property not found locally',
                    'code' => 404,
                ];
            }
            $record = DB::connection('pgsql')
                ->table('lekki_enumeration')
                ->where('property_id', $buildingCode)
                ->first();
            if (empty($record)) {
                return [
                    'success' => false,
                    'message' => 'Property not found in remote database',
                    'code' => 422,
                ];
            }
            $syncWord = $this->_extractSyncWord($record);
            $updateData = $this->_buildUpdateData($record, $syncWord);
            PropertyList::where('id', $propertyDetail->id)->update($updateData);

            $streetName = trim((string) ($updateData['street_name'] ?? ''));
            if ($streetName !== '') {
                $this->syncStreetNameToBillings($buildingCode, $streetName, (int) $propertyDetail->id);
            }

            Log::info('PropertySyncTrait: force sync successful', [
                'building_code' => $buildingCode,
                'property_id' => $propertyDetail->id,
            ]);

            return [
                'success' => true,
                'message' => 'Property synchronized successfully',
                'data' => $propertyDetail->fresh(),
            ];

        } catch (\Exception $e) {
            Log::error('PropertySyncTrait: force sync failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'building_code' => $buildingCode,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * Extract sync word from remote record (checks multiple possible fields)
     *
     * @param  object  $record  Remote database record
     * @return string|null The sync word
     */
    private function _extractSyncWord($record): ?string
    {
        $syncWordFields = [
            'residentia', 'commercial', 'industrial', 'industri_1', 'education',
            'agricultur', 'transport', 'utility', 'kgsg_publi', 'fgn_public',
            'religious', 'others',
        ];

        foreach ($syncWordFields as $field) {
            if (! is_null($record->{$field} ?? null)) {
                return $record->{$field};
            }
        }

        return null;
    }

    /**
     * Build update data array from remote record
     *
     * @param  object  $record  Remote enumeration record
     * @param  string|null  $syncWord  Property type sync word
     * @return array Update data ready for database
     */
    private function _buildUpdateData($record, $syncWord = null): array
    {

        $chargeRate = $this->_getChargeRate($record->business_name_1, $record->property_type) ?: ChargeRate::first();
        $areaVal = $this->convertToSqm('2000_Acre');
        $propertyClassification = ($chargeRate && $chargeRate->id == 7)
            ? PropertyClassification::find(2)
            : PropertyClassification::find(1);
        $syncWord = $record->property_type ?? 'Residential';
        $pavRecord = $this->_getPavCode($record->property_type);

        // $chargeRate = $this->_getChargeRate($record->occupier_s ?? null, $record->landuse ?? null) ?: ChargeRate::first();
        // $areaVal = $this->convertToSqm($record->property_area ?? '2000_Acre');
        // $propertyClassification = PropertyClassification::find($record->landuse ?? 1);
        $propertyUse = PropertyAssessmentValue::where('property_type', $record->property_type)->first();

        return [
            'address' => ($record->confirmed_house_number ?? '').' '.($record->street_name ?? ''),
            'area' => ! empty($areaVal) ? str_replace('_sqm', '', $areaVal) : 0,
            'building_code' => $record->property_id,
            'image' => $record->photo_front ?? null,
            'owner_email' => $record->owner_email ?? null,
            'owner_gsm' => $record->owner_phone ?? null,
            'owner_name' => $record->owner_name ?? null,
            'pav_code' => $propertyUse->pav_code ?? null,
            'power' => 0,
            'storey' => '',
            'water' => 0,
            'zone_name' => $propertyUse->zones ?? 'A',
            'sub_zone' => $propertyUse->zones ?? 'A1',
            'ward' => $propertyUse->zones ?? 'A1',
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
            'property_address' => ($record->confirmed_house_number ?? '').' '.($record->street_name ?? ''),
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
            'street_name' => $record->street_name ?? '',
            'assigned_house_number' => $record->assigned_house_number ?? '',
            'business_name_1' => $record->business_name_1 ?? '',
            'business_name_2' => $record->business_name_2 ?? '',
            'business_name_3' => $record->business_name_3 ?? '',
            'business_name_4' => $record->business_name_4 ?? '',
        ];
    }

    /**
     * Get zone character for update operations
     *
     * @param  string  $char  Zone code
     * @return string Zone character
     */
    protected function _getZoneCharacterCode($char): string
    {
        return match (substr($char, 0, 1)) {
            'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D',
            'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H',
            default => 'Z'
        };
    }

    /**
     * Get PAV code for update operations (uses class_id, zone, and sync_word)
     *
     * @param  int  $classId
     * @param  string  $zone
     * @param  string|null  $syncWord
     * @return PropertyAssessmentValue|null
     */
    private function _getPavCodeForUpdate($classId, $zone, $syncWord = null)
    {
        $query = PropertyAssessmentValue::where('class_id', $classId)
            ->where('zones', 'LIKE', '%'.$zone.'%');

        if ($syncWord) {
            $query->where('sync_word', $syncWord);
        }

        return $query->first();
    }
}

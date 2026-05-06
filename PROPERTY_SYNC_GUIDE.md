# PropertySyncTrait Usage Guide

## Overview
The `PropertySyncTrait` has been extracted from `SyncDataCommand` to provide a reusable, flexible property synchronization system. It can now be used in:
- Console commands
- Controllers
- Jobs
- Other services

## Key Features
✅ Sync all complete enumeration records  
✅ Sync specific property IDs (single or multiple)  
✅ Progress tracking and reporting  
✅ Error handling and exception logging  
✅ Flexible output callbacks for various contexts  

---

## Usage Examples

### 1. In Console Command (Default)

#### Sync All Complete Records
```bash
php artisan app:syncData
```

#### Sync Specific Property IDs
```bash
php artisan app:syncData --property-ids="PROP001,PROP002,PROP003"
```

#### Single Property ID
```bash
php artisan app:syncData --property-ids="PROP001"
```

### 2. In a Controller

```php
<?php

namespace App\Http\Controllers;

use App\Traits\PropertySyncTrait;
use Illuminate\Http\Request;

class PropertySyncController extends Controller
{
    use PropertySyncTrait;

    public function syncProperty(Request $request)
    {
        $propertyId = $request->input('property_id');
        
        // Sync a single property
        $stats = $this->syncPropertyData($propertyId);
        
        return response()->json([
            'message' => 'Property synchronized',
            'stats' => $stats
        ]);
    }

    public function syncMultiple(Request $request)
    {
        $propertyIds = $request->input('property_ids'); // Array: ['PROP001', 'PROP002']
        
        // Sync multiple properties
        $stats = $this->syncPropertyData($propertyIds);
        
        return response()->json([
            'message' => 'Properties synchronized',
            'stats' => $stats
        ]);
    }
}
```

### 3. In a Job

```php
<?php

namespace App\Jobs;

use App\Traits\PropertySyncTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncPropertiesJob implements ShouldQueue
{
    use Dispatchable, Queueable, PropertySyncTrait;

    protected $propertyIds;

    public function __construct($propertyIds = [])
    {
        $this->propertyIds = $propertyIds;
    }

    public function handle()
    {
        $stats = $this->syncPropertyData($this->propertyIds);
        
        \Log::info('Property sync job completed', $stats);
    }
}
```

### 4. With Custom Output Handler

```php
// In any class using the trait
$stats = $this->syncPropertyData(
    ['PROP001', 'PROP002'],
    function ($message) {
        // Custom output handling
        \Log::info('Sync: ' . $message);
        // Or send to a service, broadcast, etc.
    }
);
```

---

## Return Value

All `syncPropertyData()` calls return statistics array:

```php
[
    'synced' => 15,        // Number of successfully synced properties
    'exceptions' => 2,     // Number of properties with errors
    'total' => 17          // Total properties processed
]
```

---

## Method References

### Main Method
- **`syncPropertyData($propertyIds, $outputCallback = null)`**
  - Accept: Single ID (string/int), array of IDs, or empty array (sync all)
  - Returns: Stats array
  - Output: Via callback or through trait's internal logging

### Helper Methods (Private)
- **`_processPropertyRecord($record, $outputCallback)`** - Process individual record
- **`_buildPropertyData($record)`** - Build data array for insertion
- **`_getChargeRate($businessName, $propertyType)`** - Determine charge rate
- **`_isCommercial($businessName, $propertyType)`** - Check if property is commercial
- **`_getPavCode($classId, $zone, $syncWord)`** - Get property assessment value code

### Statistics
- **`getSyncStats()`** - Get current sync statistics

---

## Integration with SyncDataCommand

The refactored `SyncDataCommand` now:
1. Uses `PropertySyncTrait` for all sync logic
2. Accepts optional `--property-ids` argument
3. Provides formatted console output
4. Logs activity to database (if `$userId` is set)
5. Maintains backward compatibility

### Example Usage in SyncDataCommand
```php
public function handle(): void
{
    if ($this->option('property-ids')) {
        $propertyIds = array_map('trim', explode(',', $this->option('property-ids')));
    }
    
    $stats = $this->syncPropertyData($propertyIds ?? [], function ($message) {
        $this->info($message);
    });
}
```

---

## Error Handling

The trait:
- Logs all errors to `storage/logs/laravel.log`
- Marks failed records with `bill_sync = 2` in remote table
- Marks successful records with `bill_sync = 1`
- Continues processing even if individual records fail
- Returns complete statistics including exception count

---

## Performance Notes

- Uses cursor() for memory efficiency when syncing all records
- Processes one record at a time to prevent timeouts
- Direct DB updates for marking sync status
- Suitable for both small (1-10) and large (1000+) property batches

---

## Class Structure

```
SyncDataCommand
├── use PropertySyncTrait
│   ├── syncPropertyData()
│   ├── _processPropertyRecord()
│   ├── _buildPropertyData()
│   ├── _getChargeRate()
│   ├── _isCommercial()
│   ├── _getPavCode()
│   └── getSyncStats()
├── handle() [Entry point]
├── _logActivity() [Database logging]
└── failed() [Error handling]
```

---

## Example: Complete Workflow

```php
// Option 1: Sync all
php artisan app:syncData

// Option 2: Sync specific properties
php artisan app:syncData --property-ids="BUILD001,BUILD002,BUILD003"

// Expected Output:
// [1] Processing record id=1234 property_id=BUILD001
// Created PropertyList id=50 building_code=BUILD001
// [2] Processing record id=1235 property_id=BUILD002
// Created PropertyList id=51 building_code=BUILD002
// [3] Processing record id=1236 property_id=BUILD003
// Property already exists: building_code=BUILD003
// 
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ✓ Synchronization Complete
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Total Processed: 3
// Successfully Synced: 2
// Exceptions: 0
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## Benefits of Refactoring

✅ **Reusability** - Can be used in any class  
✅ **Testability** - Easier to unit test  
✅ **Maintainability** - Single source of truth  
✅ **Flexibility** - Accepts single/multiple/all IDs  
✅ **Separation of Concerns** - Trait handles sync, Command handles CLI  
✅ **Better Logging** - Comprehensive error tracking  
✅ **Cleaner Code** - Command reduced from ~300 to ~115 lines  


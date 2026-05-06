# Property Sync Diagnostic & Troubleshooting Guide

## Current Status

✅ **Fixed Issues:**
1. Missing `convertToSqm()` method - now included via UtilityTrait
2. Empty property IDs array preventing query - now handles both single, multiple, and all records
3. Silent failures - now with comprehensive debug logging

## How to Run with Full Debugging

### Option 1: Sync All Records (with real-time error output)
```bash
php artisan app:syncData
```

### Option 2: Sync Specific Properties (comma-separated)
```bash
php artisan app:syncData --property-ids="LP1-AAS-051,LP1-AAS-050"
```

### Option 3: Monitor Logs in Real-time (in separate terminal)
```bash
tail -f storage/logs/laravel.log | grep PropertySyncTrait
```

## Understanding the Output

### Success Indicators
```
✓ Created PropertyList id=123
✓ Fallback insert id=124
```

### Error Indicators
```
❌ ERROR [LP1-AAS-051]: Method does not exist
❌ ERROR [LP1-AAS-050]: Database error...
```

### Information Indicators
```
ⓘ Property already exists: LP1-AAS-051
```

## Log Locations & What They Show

### 1. Real-time Console Output
Shows immediate progress with colored indicators

### 2. `storage/logs/laravel.log`
Contains detailed debugging info:
- Data being built for each property
- Database query errors
- Stack traces for all exceptions
- Query connection info

### Debug Log Entries to Look For

**Successful flow:**
```
PropertySyncTrait: processing record
PropertySyncTrait: building property data
PropertySyncTrait: attempting insert with data
PropertySyncTrait: created PropertyList via model
```

**If model create fails:**
```
PropertySyncTrait: model create returned no ID, using fallback insert
PropertySyncTrait: fallback insert successful
```

**If both fail:**
```
PropertySyncTrait: insert operation failed completely
message: ... (database error details)
```

## Common Issues & Solutions

### Issue: "Method convertToSqm does not exist"
**Status:** ✅ FIXED in latest commit  
**Solution:** Make sure both traits are included:
```php
use PropertySyncTrait, UtilityTrait;
```

### Issue: "Processed but not inserted"
**Solution:** Check logs for specific error:
```bash
grep -i "error\|exception" storage/logs/laravel.log | tail -50
```

### Issue: "No records found"
**Possible causes:**
1. PostgreSQL connection not working
2. No records with `completeness_status = 'Complete'`
3. Property IDs not matching remote database

**Debug steps:**
```php
// Check connection in tinker
php artisan tinker
DB::connection('pgsql')->table('lekki_enumeration')->count()
DB::connection('pgsql')->table('lekki_enumeration')->where('completeness_status', 'Complete')->count()
```

### Issue: "Database constraint violation"
**Possible causes:**
1. building_code is not unique
2. Foreign key constraint on lga_id
3. Required field is null

**Debug:** Check specific error in logs with property_id

## Verification After Sync

### Check Inserted Records
```bash
php artisan tinker
PropertyList::whereNotNull('sync_word')->count()
PropertyList::latest('created_at')->limit(10)->get()
```

### Check Sync Status in Remote DB
```php
DB::connection('pgsql')->table('lekki_enumeration')
   ->where('bill_sync', 1)->count()  // Successfully synced
DB::connection('pgsql')->table('lekki_enumeration')
   ->where('bill_sync', 2)->count()  // Failed
```

## Enhanced Logging Details

The PropertySyncTrait now logs:

1. **Per-record progress:**
   - Record ID and property ID
   - Processing counter
   - Log timestamp

2. **Data building:**
   - Charge rate ID
   - Property classification
   - PAV record
   - Sync word

3. **Insert attempts:**
   - Data count
   - Model create attempt
   - Fallback insert attempt
   - Any exception details

4. **Completion stats:**
   - Total processed
   - Successfully synced
   - Exceptions count

## Quick Checklist Before Sync

- [ ] PostgreSQL connection configured in .env
- [ ] Local MySQL database running
- [ ] property_lists table exists
- [ ] Remote database has records with completeness_status = 'Complete'
- [ ] Check disk space for logs: `df -h`
- [ ] Verify Laravel environment: `php artisan tinker`

## Next Steps

1. **Run sync with monitoring:**
   ```bash
   # Terminal 1: Run sync
   php artisan app:syncData
   
   # Terminal 2: Watch logs (in separate terminal)
   tail -f storage/logs/laravel.log
   ```

2. **When you see errors, note:**
   - The property ID (e.g., LP1-AAS-051)
   - The exact error message
   - Share both with debugging team

3. **If successful, verify:**
   ```bash
   php artisan tinker
   PropertyList::count()  # Should show inserted records
   ```

## Contact/Debug Info to Share

When reporting issues, include:
1. Full error message from console
2. Stack trace from logs
3. Property ID that failed
4. Count of total processed/synced/exceptions
5. Database sizes:
   - Remote lekki_enumeration count
   - Local property_lists count

## Performance Notes

- **Cursor streaming:** Reduces memory usage for large datasets
- **Each record processed individually:** Allows better error tracking
- **Logging overhead:** Debug logs will slow processing ~10-20%
- **Estimated speed:** ~50-100 records/minute with logging

---

**Created:** May 6, 2026  
**Last Updated:** After PropertySyncTrait refactoring  
**Version:** 1.0


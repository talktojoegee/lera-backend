# Export Performance Fix - Deployment Steps

## Problem Summary
- PHP 512MB memory limit too low for 50k+ row exports
- Export job fails with: `Allowed memory size of 536870912 bytes exhausted`
- Deprecated parameter warning in UtilityTrait

## Code Changes Applied (locally)
1. ✅ Fixed deprecated parameter ordering in `app/Traits/UtilityTrait.php` 
2. ✅ Fixed deprecated parameter ordering in `app/Http/Controllers/PaymentValidationController.php`
3. ✅ Added `config/excel.php` with writable temp path
4. ✅ Increased memory to 2GB in `app/Jobs/BulkExportBillByTypeJob.php`
5. ✅ Reduced chunk size to 500 (from 1000) in export classes

## Server Deployment Checklist

### 1. Deploy code changes
```bash
cd /var/www/api.kslas.ng/public

# Pull latest changes
git pull origin main

# Install any composer dependencies
composer install --no-dev --optimize-autoloader

# Clear config cache
php artisan config:clear
php artisan cache:clear
```

### 2. Update Supervisor worker configuration

Edit `/etc/supervisor/conf.d/laravel-worker.conf` and set memory/timeout for **all queues**:

```ini
[program:api-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api.kslas.ng/public/artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=1800 --memory=1024
numprocs=5

[program:data_sync_queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api.kslas.ng/public/artisan queue:work database --queue=data_sync_queue --sleep=3 --tries=2 --timeout=1800 --memory=1024
numprocs=1

[program:print_queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api.kslas.ng/public/artisan queue:work database --queue=print_queue --sleep=3 --tries=2 --timeout=1800 --memory=1024
numprocs=1

[program:mail_queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api.kslas.ng/public/artisan queue:work database --queue=mail_queue --sleep=3 --tries=2 --timeout=1800 --memory=512
numprocs=1
```

**Key changes:**
- `--timeout=1800` (30 min, up from default 60s)
- `--memory=1024` (1GB per worker, up from default 128MB)
- Keep `--tries=2` for exports

### 3. Reload Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all
```

### 4. Verify workers are running with correct limits
```bash
sudo supervisorctl status
```

Output should show all workers `RUNNING`:
```
api-queue:api-queue_00    RUNNING   pid ..., uptime ...
data_sync_queue:...       RUNNING   ...
print_queue:...           RUNNING   ...
mail_queue:...            RUNNING   ...
```

### 5. Restart failed jobs (optional)
```bash
cd /var/www/api.kslas.ng/public
php artisan queue:retry all
```

### 6. Test the export flow
```bash
# Trigger export
curl -s 'https://api.kslas.ng/api/export-bills/5/normal-outstanding'

# Take the export_id from response, then poll status
export ID=<export_id>
curl -s "https://api.kslas.ng/api/export-status/$ID" | jq .

# When status is 'completed', download
curl -L -o /tmp/billings.xlsx "https://api.kslas.ng/api/export-download/$ID"
```

## Troubleshooting

### If export still fails with memory error:
```bash
cd /var/www/api.kslas.ng/public
php artisan queue:failed
php artisan tinker --execute="dump(DB::table('failed_jobs')->orderByDesc('id')->first(['exception']))"
```

Increase `--memory` to `2048` in Supervisor config and retry.

### If deprecation warning still appears:
Verify the file was deployed:
```bash
grep -n "_registerInPaymentLog(\$billMasterId, \$paidBy[,)]" /var/www/api.kslas.ng/public/app/Traits/UtilityTrait.php
```

Should show line 213 with NO default value (`$paidBy` not `$paidBy = 5`).

### Monitor export status
```bash
# Real-time worker logs
tail -f /var/www/api.kslas.ng/public/storage/logs/laravel.log | grep -i "export\|memory\|failed"
```

## Config Files Modified
- `app/Traits/UtilityTrait.php` — removed optional default before required param
- `app/Http/Controllers/PaymentValidationController.php` — removed optional default before required param
- `config/excel.php` — new, sets temp dir to writable OS path
- `app/Jobs/BulkExportBillByTypeJob.php` — increased memory to 2GB at runtime
- `app/Exports/BillingExport.php` — reduced chunk size to 500
- `app/Exports/PaidBillExport.php` — reduced chunk size to 500


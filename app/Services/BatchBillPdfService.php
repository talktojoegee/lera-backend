<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\PrintBillBatch;
use App\Models\PrintBillLog;
use App\Models\RoutePayQrRegistration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BatchBillPdfService
{
    public function queueGeneration(string $batchCode): PrintBillBatch
    {
        $batch = $this->ensureBatchRecord($batchCode);

        if ($batch->isReady() && Storage::disk($this->disk())->exists($batch->pdf_path)) {
            return $batch;
        }

        if ($batch->pdf_status === PrintBillBatch::STATUS_FAILED) {
            $batch->update([
                'pdf_status' => PrintBillBatch::STATUS_PENDING,
                'pdf_error' => null,
            ]);
        }

        if (! in_array($batch->pdf_status, [PrintBillBatch::STATUS_PROCESSING, PrintBillBatch::STATUS_COMPLETED], true)) {
            \App\Jobs\GenerateBatchBillPdfJob::dispatch($batchCode);
        }

        return $batch->fresh();
    }

    public function regenerate(string $batchCode): PrintBillBatch
    {
        $batch = $this->ensureBatchRecord($batchCode);
        $disk = $this->disk();

        if ($batch->pdf_path && Storage::disk($disk)->exists($batch->pdf_path)) {
            Storage::disk($disk)->delete($batch->pdf_path);
        }

        $batch->update([
            'pdf_status' => PrintBillBatch::STATUS_PENDING,
            'pdf_path' => null,
            'pdf_error' => null,
            'pdf_generated_at' => null,
        ]);

        \App\Jobs\GenerateBatchBillPdfJob::dispatch($batchCode);

        return $batch->fresh();
    }

    public function statusForBatch(string $batchCode): array
    {
        $batch = PrintBillBatch::query()->where('batch_code', $batchCode)->first();

        if ($batch === null) {
            $billCount = PrintBillLog::query()->where('batch_code', $batchCode)->count();
            if ($billCount === 0) {
                return [
                    'batchCode' => $batchCode,
                    'pdfStatus' => 'missing',
                    'pdfReady' => false,
                    'billCount' => 0,
                    'error' => 'Batch not found.',
                ];
            }

            $batch = $this->queueGeneration($batchCode);
        }

        return [
            'batchCode' => $batch->batch_code,
            'pdfStatus' => $batch->pdf_status,
            'pdfReady' => $batch->isReady() && Storage::disk($this->disk())->exists((string) $batch->pdf_path),
            'billCount' => $batch->bill_count,
            'generatedAt' => $batch->pdf_generated_at?->toIso8601String(),
            'error' => $batch->pdf_error,
        ];
    }

    public function resolveDownloadResponse(string $batchCode): Response|JsonResponse|BinaryFileResponse
    {
        $batch = PrintBillBatch::query()->where('batch_code', $batchCode)->first();

        if ($batch === null) {
            $billCount = PrintBillLog::query()->where('batch_code', $batchCode)->count();
            if ($billCount === 0) {
                return response()->json(['error' => 'No bills found for this batch.'], 404);
            }

            $batch = $this->queueGeneration($batchCode);
        }

        if ($batch->isReady() && Storage::disk($this->disk())->exists((string) $batch->pdf_path)) {
            $absolutePath = Storage::disk($this->disk())->path($batch->pdf_path);

            return response()->download(
                $absolutePath,
                'Bill_'.$batchCode.'.pdf',
                ['Content-Type' => 'application/pdf'],
                'attachment'
            );
        }

        if ($batch->pdf_status === PrintBillBatch::STATUS_FAILED) {
            return response()->json([
                'status' => PrintBillBatch::STATUS_FAILED,
                'error' => $batch->pdf_error ?? 'PDF generation failed.',
            ], 422);
        }

        if ($batch->pdf_status !== PrintBillBatch::STATUS_PROCESSING) {
            $this->queueGeneration($batchCode);
        }

        return response()->json([
            'status' => $batch->fresh()->pdf_status,
            'message' => 'PDF is being generated in the background. Please try again shortly.',
            'batchCode' => $batchCode,
        ], 202);
    }

    public function generateAndStore(string $batchCode): void
    {
        $batch = $this->ensureBatchRecord($batchCode);

        if ($batch->isReady() && Storage::disk($this->disk())->exists((string) $batch->pdf_path)) {
            return;
        }

        $batch->update([
            'pdf_status' => PrintBillBatch::STATUS_PROCESSING,
            'pdf_error' => null,
        ]);

        try {
            $this->prepareRuntimeLimits();
            $records = $this->fetchRecordsForBatch($batchCode);

            if ($records->isEmpty()) {
                throw new \RuntimeException('No bill records found for this batch.');
            }

            $pdfBinary = $this->buildPdfBinary($records);
            $relativePath = $this->storagePathForBatch($batchCode);

            Storage::disk($this->disk())->put($relativePath, $pdfBinary);

            $batch->update([
                'pdf_status' => PrintBillBatch::STATUS_COMPLETED,
                'pdf_path' => $relativePath,
                'pdf_error' => null,
                'pdf_generated_at' => now(),
                'bill_count' => $records->count(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Batch bill PDF generation failed.', [
                'batch_code' => $batchCode,
                'error' => $exception->getMessage(),
            ]);

            $batch->update([
                'pdf_status' => PrintBillBatch::STATUS_FAILED,
                'pdf_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function ensureBatchRecord(string $batchCode): PrintBillBatch
    {
        $existing = PrintBillBatch::query()->where('batch_code', $batchCode)->first();
        if ($existing !== null) {
            return $existing;
        }

        $firstLog = PrintBillLog::query()->where('batch_code', $batchCode)->orderBy('id')->first();
        $billCount = PrintBillLog::query()->where('batch_code', $batchCode)->count();

        return PrintBillBatch::query()->create([
            'batch_code' => $batchCode,
            'user_id' => $firstLog?->user_id,
            'printed_by' => $firstLog?->printed_by,
            'bill_count' => $billCount,
            'pdf_status' => PrintBillBatch::STATUS_PENDING,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    protected function fetchRecordsForBatch(string $batchCode): Collection
    {
        $printLogIds = PrintBillLog::query()->where('batch_code', $batchCode)->pluck('bill_id')->toArray();

        return DB::table('billings')
            ->join('property_lists', 'billings.property_id', '=', 'property_lists.id')
            ->join('property_classifications', 'billings.class_id', '=', 'property_classifications.id')
            ->select([
                'billings.id as billId',
                'property_lists.property_name as propertyName',
                'property_lists.owner_kgtin as kgTin',
                'property_lists.owner_gsm as mobileNo',
                'property_lists.property_address as propertyAddress',
                'property_lists.address as address',
                'billings.cr as chargeRate',
                'billings.class_id as class_id',
                'billings.building_code as buildingCode',
                'billings.entry_date as entryDate',
                'billings.assessment_no as assessmentNo',
                'billings.year as year',
                'billings.lga_id as lgaId',
                'billings.assessed_value as assessedValue',
                'billings.bill_amount as billAmount',
                'billings.paid_amount as paidAmount',
                'property_classifications.class_name as className',
                'property_lists.property_use as propertyUse',
            ])
            ->whereIn('billings.id', $printLogIds)
            ->get();
    }

    /**
     * @param  Collection<int, object>  $records
     */
    protected function buildPdfBinary(Collection $records): string
    {
        $bbfMap = $this->batchBalanceBroughtForward($records);
        $billsByAssessment = Billing::query()
            ->whereIn('id', $records->pluck('billId')->all())
            ->get()
            ->keyBy('assessment_no');

        $qrTempPaths = [];
        try {
            foreach ($records as $record) {
                $bbfKey = $record->buildingCode.'|'.$record->year;
                $record->bbf = $bbfMap[$bbfKey] ?? 0.0;
                $this->applyBillTotals($record);

                $bill = $billsByAssessment->get($record->assessmentNo);
                $tempPath = $this->attachRoutePayQrToBillRecord($record, $bill);
                if ($tempPath !== null) {
                    $qrTempPaths[] = $tempPath;
                }

                $record->qrImage = $this->embedQrImageForPdf($record->qrImage ?? null, $bill?->id);
            }

            return $this->renderBatchBillPdf($records);
        } finally {
            $this->cleanupTempPaths($qrTempPaths);
        }
    }

    protected function applyBillTotals(object $record): void
    {
        if (! isset($record->bbf)) {
            $record->bbf = $this->balanceBroughtForward((int) $record->year, (string) $record->buildingCode);
        }

        $paidAmount = (float) ($record->paidAmount ?? 0);
        $currentYearDue = max(0.0, (float) $record->billAmount - $paidAmount);
        $record->totalPayable = max(0.0, (float) $record->bbf + $currentYearDue);
    }

    public function attachRoutePayQrToBillRecord(object $record, ?Billing $bill = null): ?string
    {
        $assessmentNo = trim((string) ($record->assessmentNo ?? ''));
        if ($assessmentNo === '') {
            $record->qrImage = null;

            return null;
        }

        $bill ??= Billing::query()->where('assessment_no', $assessmentNo)->first();

        $qrService = app(BillPaymentQrService::class);
        $paymentUrl = $qrService->paymentUrlForRecord($record);
        if ($paymentUrl === null) {
            $record->qrImage = null;

            return null;
        }

        $record->qrImage = $qrService->qrImageUrlForRecord($record);

        return null;
    }

    protected function resolveBillPaymentQrFallbackUrl(object $record, float $totalPayable): ?string
    {
        return app(BillPaymentQrService::class)->paymentUrlForRecord($record);
    }

    protected function generateQRCode(string $text, int $size = 150): string
    {
        return app(BillPaymentQrService::class)->qrImageUrlForPaymentUrl($text, $size);
    }

    /**
     * @param  list<string|null>  $paths
     */
    protected function cleanupTempPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    protected function balanceBroughtForward(int $year, string $buildingCode): float
    {
        return (float) (Billing::query()
            ->where('building_code', $buildingCode)
            ->where('year', '<', $year)
            ->where(function ($query) {
                $query->where('paid', 0)
                    ->orWhereColumn('paid_amount', '<', 'bill_amount');
            })
            ->select(DB::raw('SUM(bill_amount - paid_amount) as balance'))
            ->value('balance') ?? 0.0);
    }

    protected function prepareRuntimeLimits(): void
    {
        $maxSeconds = (int) config('billing.batch_pdf_max_execution_seconds', 0);
        if ($maxSeconds >= 0) {
            @set_time_limit($maxSeconds);
        }

        $memoryLimit = (string) config('billing.batch_pdf_memory_limit', '1024M');
        if ($memoryLimit !== '') {
            @ini_set('memory_limit', $memoryLimit);
        }
    }

    /**
     * @param  Collection<int, object>  $records
     * @return array<string, float>
     */
    protected function batchBalanceBroughtForward(Collection $records): array
    {
        $buildingCodes = $records->pluck('buildingCode')->filter()->unique()->values();
        if ($buildingCodes->isEmpty()) {
            return [];
        }

        $rows = Billing::query()
            ->whereIn('building_code', $buildingCodes)
            ->where(function ($query) {
                $query->where('paid', 0)
                    ->orWhereColumn('paid_amount', '<', 'bill_amount');
            })
            ->select('building_code', 'year', DB::raw('SUM(bill_amount - paid_amount) as balance'))
            ->groupBy('building_code', 'year')
            ->get();

        $balancesByBuildingYear = [];
        foreach ($rows as $row) {
            $balancesByBuildingYear[$row->building_code][(int) $row->year] = (float) $row->balance;
        }

        $result = [];
        foreach ($records as $record) {
            $buildingCode = (string) ($record->buildingCode ?? '');
            $year = (int) ($record->year ?? 0);
            $bbf = 0.0;

            if ($buildingCode !== '' && isset($balancesByBuildingYear[$buildingCode])) {
                foreach ($balancesByBuildingYear[$buildingCode] as $priorYear => $balance) {
                    if ($priorYear < $year) {
                        $bbf += $balance;
                    }
                }
            }

            $result[$buildingCode.'|'.$year] = $bbf;
        }

        return $result;
    }

    /**
     * @param  Collection<int, object>  $records
     */
    protected function renderBatchBillPdf(Collection $records): string
    {
        $chunkSize = max(0, (int) config('billing.batch_pdf_chunk_size', 50));
        $needsMerge = $chunkSize > 0 && $records->count() > $chunkSize;

        if (! $needsMerge) {
            return $this->renderBillPdfChunk($records);
        }

        if (! class_exists(Fpdi::class)) {
            Log::warning('Batch bill PDF: setasign/fpdi is not installed; rendering all bills in one pass. Run: composer require setasign/fpdi setasign/fpdf');

            return $this->renderBillPdfChunk($records);
        }

        $tempFiles = [];
        try {
            foreach ($records->chunk($chunkSize) as $chunk) {
                $tempPath = tempnam(sys_get_temp_dir(), 'lera_bill_pdf_');
                if ($tempPath === false) {
                    throw new \RuntimeException('Unable to create temporary PDF file.');
                }
                file_put_contents($tempPath, $this->renderBillPdfChunk($chunk));
                $tempFiles[] = $tempPath;
            }

            return $this->mergePdfFiles($tempFiles);
        } finally {
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }
    }

    /**
     * @param  Collection<int, object>  $records
     */
    /**
     * DomPDF must read images from public/ using paths relative to that directory.
     *
     * @return array<string, mixed>
     */
    public function dompdfOptions(): array
    {
        return [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'chroot' => realpath(public_path()) ?: public_path(),
            'defaultFont' => 'Arial',
        ];
    }

    public function leraBackgroundForLga(int $lgaId): string
    {
        return match ($lgaId) {
            1 => 'assets/images/bg_newwaves.png',
            2 => 'assets/images/bg_alphacen.png',
            default => 'assets/images/background-22.png',
        };
    }

    protected function renderBillPdfChunk(Collection $records): string
    {
        return Pdf::loadView('pdf.bill', ['records' => $records])
            ->setPaper('A4', 'portrait')
            ->setOptions($this->dompdfOptions())
            ->output();
    }

    /**
     * @param  list<string>  $paths
     */
    protected function mergePdfFiles(array $paths): string
    {
        if ($paths === []) {
            return '';
        }

        if (count($paths) === 1) {
            return (string) file_get_contents($paths[0]);
        }

        if (! class_exists(Fpdi::class)) {
            throw new \RuntimeException(
                'PDF merge requires setasign/fpdi and setasign/fpdf. On the server run: composer require setasign/fpdi setasign/fpdf'
            );
        }

        $merger = new Fpdi;
        foreach ($paths as $path) {
            $pageCount = $merger->setSourceFile($path);
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $merger->importPage($page);
                $size = $merger->getTemplateSize($template);
                $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merger->useTemplate($template);
            }
        }

        return $merger->Output('S');
    }

    /**
     * Persist QR PNG under public/ and return a path relative to public/ for DomPDF.
     */
    public function persistQrImageForBill(?int $billId, ?string $qrImage): ?string
    {
        if ($billId === null || ! is_string($qrImage) || $qrImage === '') {
            return null;
        }

        if (str_starts_with($qrImage, 'data:image/')) {
            return $this->writeQrBinaryToPublic($billId, base64_decode(
                (string) preg_replace('#^data:image/\w+;base64,#', '', $qrImage)
            ) ?: '');
        }

        $relativePath = $this->qrPublicRelativePath($billId);
        $absolutePath = public_path($relativePath);
        if (
            ! str_starts_with($qrImage, 'http')
            && $this->isValidQrPublicFile($absolutePath)
        ) {
            $this->updateRegistrationQrImagePath($billId, $relativePath);

            return $relativePath;
        }

        if (str_starts_with($qrImage, 'bill-qr-images/')) {
            $storagePath = storage_path('app/'.$qrImage);
            if (is_readable($storagePath)) {
                return $this->writeQrBinaryToPublic($billId, (string) file_get_contents($storagePath));
            }

            if (is_readable(public_path($qrImage))) {
                return $qrImage;
            }
        }

        if (is_readable($qrImage) && ! str_starts_with($qrImage, 'http')) {
            return $this->toPublicRelativePath($qrImage);
        }

        if (! str_starts_with($qrImage, 'http')) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get($qrImage);
            if (! $response->successful()) {
                Log::warning('Bill PDF: failed to download QR image for storage.', [
                    'bill_id' => $billId,
                    'url' => $qrImage,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->body();
            if (! $this->isPngBinary($body)) {
                Log::warning('Bill PDF: QR download is not a PNG.', [
                    'bill_id' => $billId,
                    'url' => $qrImage,
                ]);

                return null;
            }

            Storage::disk($this->disk())->put('bill-qr-images/'.$billId.'.png', $body);

            return $this->writeQrBinaryToPublic($billId, $body);
        } catch (\Throwable $exception) {
            Log::warning('Bill PDF: failed to persist QR image.', [
                'bill_id' => $billId,
                'url' => $qrImage,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve QR to a path relative to public/ that DomPDF can load.
     */
    public function embedQrImageForPdf(?string $qrImage, ?int $billId = null): ?string
    {
        if (! is_string($qrImage) || $qrImage === '') {
            return null;
        }

        if ($billId !== null) {
            $relativePath = $this->persistQrImageForBill($billId, $qrImage);
            if ($relativePath !== null) {
                return $relativePath;
            }

            if (str_starts_with($qrImage, 'http')) {
                return $qrImage;
            }

            if (str_starts_with($qrImage, 'data:image/')) {
                return $qrImage;
            }

            return null;
        }

        if (str_starts_with($qrImage, 'bill-qr-images/') && $this->isValidQrPublicFile(public_path($qrImage))) {
            return $qrImage;
        }

        if (is_readable($qrImage) && ! str_starts_with($qrImage, 'http')) {
            return $this->toPublicRelativePath($qrImage);
        }

        if (str_starts_with($qrImage, 'http')) {
            $tempBillId = abs(crc32($qrImage));
            $persisted = $this->persistQrImageForBill($tempBillId, $qrImage);

            return $persisted ?? $qrImage;
        }

        if (str_starts_with($qrImage, 'data:image/')) {
            return $qrImage;
        }

        return null;
    }

    protected function writeQrBinaryToPublic(int $billId, string $body): ?string
    {
        if ($body === '' || ! $this->isPngBinary($body)) {
            return null;
        }

        $directory = public_path('bill-qr-images');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return null;
        }

        $publicPath = $this->qrPublicAbsolutePath($billId);
        file_put_contents($publicPath, $body);
        $relativePath = $this->qrPublicRelativePath($billId);
        $this->updateRegistrationQrImagePath($billId, $relativePath);

        return $relativePath;
    }

    protected function toPublicRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $publicRoot = str_replace('\\', '/', realpath(public_path()) ?: public_path());

        if (str_starts_with($normalized, $publicRoot.'/')) {
            return substr($normalized, strlen($publicRoot) + 1);
        }

        return $normalized;
    }

    protected function qrPublicAbsolutePath(int $billId): string
    {
        return public_path('bill-qr-images/'.$billId.'.png');
    }

    protected function qrPublicRelativePath(int $billId): string
    {
        return 'bill-qr-images/'.$billId.'.png';
    }

    protected function updateRegistrationQrImagePath(int $billId, string $relativePath): void
    {
        RoutePayQrRegistration::query()
            ->where('bill_id', $billId)
            ->update(['qr_image_path' => $relativePath]);
    }

    protected function isPngBinary(string $body): bool
    {
        return str_starts_with($body, "\x89PNG\r\n\x1a\n");
    }

    protected function isValidQrPublicFile(string $path): bool
    {
        if (! is_readable($path)) {
            return false;
        }

        $body = (string) file_get_contents($path);

        return $body !== '' && $this->isPngBinary($body);
    }

    protected function disk(): string
    {
        return (string) config('billing.batch_pdf_disk', 'local');
    }

    protected function storagePathForBatch(string $batchCode): string
    {
        return 'batch-pdfs/'.$batchCode.'.pdf';
    }
}

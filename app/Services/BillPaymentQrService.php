<?php

namespace App\Services;

class BillPaymentQrService
{
    /**
     * URL template for bill payment QR codes.
     * Placeholders: {assessment_no}, {building_code}, {amount}, {year}
     */
    public function paymentUrlTemplate(): string
    {
        $template = config('billing.payment_qr_url');
        if (! is_string($template) || trim($template) === '') {
            $template = config('billing.payment_qr_fallback_url');
        }

        return is_string($template) ? trim($template) : '';
    }

    public function paymentUrlForAssessment(string $assessmentNo, ?object $record = null): ?string
    {
        $assessmentNo = trim($assessmentNo);
        if ($assessmentNo === '') {
            return null;
        }

        $template = $this->paymentUrlTemplate();
        if ($template === '') {
            return null;
        }

        return strtr($template, $this->placeholderReplacements($assessmentNo, $record));
    }

    public function paymentUrlForRecord(object $record): ?string
    {
        return $this->paymentUrlForAssessment((string) ($record->assessmentNo ?? ''), $record);
    }

    /**
     * QuickChart image URL for any text (same pattern as legacy apps).
     */
    public function qrImageUrlForText(string $text, ?int $size = null): string
    {
        $size = $size ?? $this->pixelSize();

        return 'https://quickchart.io/qr?text='.urlencode($text)."&size={$size}";
    }

    public function qrImageUrlForPaymentUrl(string $paymentUrl, ?int $size = null): string
    {
        return $this->qrImageUrlForText($paymentUrl, $size);
    }

    public function qrImageUrlForRecord(object $record, ?int $size = null): ?string
    {
        $paymentUrl = $this->paymentUrlForRecord($record);
        if ($paymentUrl === null) {
            return null;
        }

        return $this->qrImageUrlForPaymentUrl($paymentUrl, $size);
    }

    /**
     * @return array<string, string>
     */
    protected function placeholderReplacements(string $assessmentNo, ?object $record): array
    {
        $totalPayable = (float) ($record->totalPayable ?? 0);

        return [
            '{assessment_no}' => $assessmentNo,
            '{building_code}' => trim((string) ($record->buildingCode ?? '')),
            '{amount}' => number_format($totalPayable, 2, '.', ''),
            '{year}' => trim((string) ($record->year ?? '')),
        ];
    }

    protected function pixelSize(?int $size = null): int
    {
        return max(120, $size ?? (int) config('billing.payment_qr_size', 180));
    }
}

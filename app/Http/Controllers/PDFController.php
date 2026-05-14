<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\PrintBillLog;
use App\Models\PropertyList;
use App\Services\RoutePayQrService;
use App\Traits\UtilityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PDFController extends Controller
{
    use UtilityTrait;

    public function generateDomPdf(Request $request)
    {
        $batchCode = $request->batchCode ?? '';
        $printLogIds = PrintBillLog::where('batch_code', $batchCode)->pluck('bill_id')->toArray();
        $records = DB::table('billings')
            ->join('property_lists', 'billings.property_id', '=', 'property_lists.id')
            ->join('property_classifications', 'billings.class_id', '=', 'property_classifications.id')
            ->select([
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
                'billings.assessed_value as assessedValue',
                'billings.bill_amount as billAmount',
                'billings.paid_amount as paidAmount',
                'property_classifications.class_name as className',
            ])
            ->whereIn('billings.id', $printLogIds)
            ->get();
        foreach ($records as $record) {
            $record->bbf = $this->balanceBroughtForward(
                $record->year,
                $record->buildingCode
            );
            $paidAmount = (float) ($record->paidAmount ?? 0);
            $currentYearDue = max(0.0, (float) $record->billAmount - $paidAmount);
            $record->totalPayable = max(0.0, (float) $record->bbf + $currentYearDue);
        }
        $pdf = Pdf::loadView('pdf.bill', compact('records'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);
    }

    public function generatePDFByAssessmentNo(Request $request)
    {

        $assessmentNo = $request->route('assessmentNo', $request->assessmentNo ?? '');
        $record = DB::table('billings')
            ->join('property_lists', 'property_lists.id', '=', 'billings.property_id')
            ->join('property_classifications', 'property_classifications.id', '=', 'billings.class_id')
            ->select([
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
                'billings.assessed_value as assessedValue',
                'billings.bill_amount as billAmount',
                'billings.paid_amount as paidAmount',
                'property_classifications.class_name as className',
            ])
            ->where('billings.assessment_no', $assessmentNo)
            ->first();
        if (empty($record)) {
            return \response()->json(['error' => 'No record found'], 404);
        }
        $bbf = $this->balanceBroughtForward($record->year, $record->buildingCode);
        $record->bbf = $bbf;
        $paidAmount = (float) ($record->paidAmount ?? 0);
        $currentYearDue = max(0.0, (float) $record->billAmount - $paidAmount);
        $totalPayable = max(0.0, $bbf + $currentYearDue);
        $record->totalPayable = $totalPayable;
        $qr = app(RoutePayQrService::class)->buildBillQrImageForPdf(
            mb_substr((string) ($record->propertyName ?? $record->assessmentNo ?? 'Bill'), 0, 120),
            $totalPayable,
            RoutePayQrService::merchantReferenceForBill((string) ($record->assessmentNo ?? 'NA'), $record->year ?? null)
        );
        $record->qrImage = $qr['src'];
        $qrTempPath = $qr['temp_path'];

        if ($record->qrImage === null && $totalPayable > 0) {
            $fallbackUrl = $this->resolveBillPaymentQrFallbackUrl($record, $totalPayable);
            if ($fallbackUrl !== null) {
                $record->qrImage = $this->generateQRCode($fallbackUrl, 180);
            } else {
                Log::warning('Bill PDF: no payment QR — RoutePay did not return a QR image. Configure ROUTEPAY_* (see RoutePay guide) or set BILL_PAYMENT_QR_FALLBACK_URL to a public payment page.', [
                    'assessment_no' => $record->assessmentNo ?? null,
                    'total_payable' => $totalPayable,
                ]);
            }
        }

        try {
            $pdf = Pdf::loadView('pdf.bill-single', compact('record'))
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'Arial',
                ]);
            $output = $pdf->output();
        } finally {
            if ($qrTempPath !== null && is_file($qrTempPath)) {
                @unlink($qrTempPath);
            }
        }

        return response($output, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Bill.pdf"',
        ]);
    }

    public function showReceipt(Request $request)
    {
        $receiptNo = $request->receipt;
        if (! isset($receiptNo)) {
            return \response()->json(['error' => 'Receipt number is required'], 422);
        }
        $receipt = BillPaymentLog::where('receipt_no', $receiptNo)->first();
        if (empty($receipt)) {
            return 'Invalid Receipt No.';
        }
        $pdf = $this->getPdf($receiptNo, $receipt);

        return $pdf->stream($receipt->receipt_no.'.pdf');
    }

    public function downloadReceipt(Request $request)
    {
        $receiptNo = $request->receipt;
        if (! isset($receiptNo)) {
            return \response()->json(['error' => 'Receipt number is required'], 422);
        }
        $receipt = BillPaymentLog::where('receipt_no', $receiptNo)->first();
        if (empty($receipt)) {
            return \response()->json(['error' => 'No record found'], 404);
        }
        $pdf = $this->getPdf($receiptNo, $receipt);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Receipt.pdf"',
        ]);
    }

    public function generateQRCode($text, $size = 150)
    {
        $encodedText = urlencode($text);

        return "https://quickchart.io/qr?text={$encodedText}&size={$size}";
    }

    public function getPdf(mixed $receiptNo, $receipt): \Barryvdh\DomPDF\PDF
    {
        $qr = $this->generateQRCode("http://api.kslas.ng/receipt/{$receiptNo}");
        $data = [
            'payer_id' => $receipt->getBill->getPropertyList->owner_kgtin ?? '', // $receipt->kgtin ?? '',
            'paid_by' => $receipt->customer_name ?? '',
            'address' => $receipt->getBill->getPropertyList->property_address ?? '',
            'assessmentNo' => $receipt->assessment_no ?? '',
            'propertyId' => $receipt->building_code ?? '',
            'amount' => 'NGN'.number_format(round($receipt->amount) ?? 0),
            'amount_words' => $this->numberToWords(round($receipt->amount) ?? 0),
            'payment_purpose' => "Payment for Land Use Charge (Revenue Code: 12020908) {$receipt->getBill->year}",
            'receipt_no' => $receipt->receipt_no ?? '',
            'invoice_no' => 'AST91574/34512/P1/2/2025',
            'swift_code' => $receipt->reference ?? '',
            'payment_date' => date('d/m/Y', strtotime($receipt->entry_date)),
            'agency' => 'KOGI STATE INTERNAL REVENUE SERVICE (KGIRS)',
            'tax_station' => $receipt->ward ?? '',
            'pay_mode' => $receipt->pay_mode ?? '',
            'authorized_signatory' => 'Sule Saliu Enehe',
            'designation' => 'Executive Chairman (KGIRS)',
            'qr' => $qr,
            'bankName' => $receipt->bank_name ?? '',
            'background' => asset('/assets/images/receipt.png'),
        ];
        $pdf = Pdf::loadView('pdf.receipt', $data)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

        return $pdf;
    }

    public function showCustomerStatementReportPDF()
    {
        return view('pdf.customer-report');
    }

    public function generateCustomerStatementReportPDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'buildingCode' => 'required',
            'from' => 'required',
            'to' => 'required',
        ], [
            'buildingCode.required' => 'Building code is required',
            'from.required' => 'Set a start date',
            'to.required' => 'Set an end date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        $buildingCode = $request->buildingCode;
        $from = $request->from;
        $to = $request->to;
        $bills = BillPaymentLog::getCustomerStatementByKgtinDate($buildingCode, $from, $to);
        $records = [];

        foreach ($bills as $index => $billing) {
            $bill = Billing::find($billing->bill_master);
            $narration = 'N/A';
            if (! empty($bill)) {
                $pDate = date('d M, Y', strtotime($billing->entry_date));
                $narration = "LUC: {$bill->bill_amount}, Payment Date: {$pDate}, BUEAREU OF LANDS, Mode of Payment: {$billing->pay_mode},
            Assessment No.: {$bill->assessment_no}, Transaction Reference: {$bill->trans_ref}, Year: {$bill->year}";
            }
            $records[] = [
                'serial' => $index + 1,
                'date' => date('d/M/Y', strtotime($billing->entry_date)),
                'amount' => number_format($billing->amount, 2),
                'channel' => $billing->pay_mode,
                'receipt' => $billing->receipt_no,
                'narration' => $narration,
            ];
        }
        $property = PropertyList::where('building_code', $buildingCode)->first();
        $headerInfo['buildingCode'] = $buildingCode;
        $headerInfo['from'] = date('d/m/Y', strtotime($from));
        $headerInfo['to'] = date('d/m/Y', strtotime($to));
        $headerInfo['payerId'] = ! empty($property) ? $property->owner_kgtin : '';
        $headerInfo['address'] = ! empty($property) ? $property->property_address : '';
        $pdf = Pdf::loadView('pdf.customer-report', compact('records', 'headerInfo'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);
    }

    /**
     * Public URL for optional non-RoutePay QR (must be https in production).
     */
    protected function resolveBillPaymentQrFallbackUrl(object $record, float $totalPayable): ?string
    {
        $template = config('billing.payment_qr_fallback_url');
        if (! is_string($template) || trim($template) === '') {
            return null;
        }

        return strtr($template, [
            '{assessment_no}' => rawurlencode((string) ($record->assessmentNo ?? '')),
            '{building_code}' => rawurlencode((string) ($record->buildingCode ?? '')),
            '{amount}' => rawurlencode(number_format($totalPayable, 2, '.', '')),
            '{year}' => rawurlencode((string) ($record->year ?? '')),
        ]);
    }

    public function balanceBroughtForward(int $year, string $buildingCode): float
    {
        return Billing::where('building_code', $buildingCode)
            ->where('year', '<', $year)
            ->where(function ($query) {
                $query->where('paid', 0)
                    ->orWhereColumn('paid_amount', '<', 'bill_amount');
            })
            ->select(DB::raw('SUM(bill_amount - paid_amount) as balance'))
            ->value('balance') ?? 0.0;
    }
}

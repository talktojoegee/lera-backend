<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\PropertyList;
use App\Models\User;
use App\Services\BatchBillPdfService;
use App\Services\BillPaymentQrService;
use App\Traits\UtilityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PDFController extends Controller
{
    use UtilityTrait;

    public function generateDomPdf(Request $request, BatchBillPdfService $batchBillPdfService)
    {
        $batchCode = $request->route('batchCode', $request->batchCode ?? '');

        return $batchBillPdfService->resolveDownloadResponse($batchCode);
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
                'billings.lga_id as lgaId',
                'billings.assessed_value as assessedValue',
                'billings.bill_amount as billAmount',
                'billings.paid_amount as paidAmount',
                'property_classifications.class_name as className',
                'property_lists.property_use as propertyUse',
            ])
            ->where('billings.assessment_no', $assessmentNo)
            ->first();
        if (empty($record)) {
            return \response()->json(['error' => 'No record found'], 404);
        }

        $this->applyBillTotals($record);
        $this->attachRoutePayQrToBillRecord($record);

        $bill = Billing::query()->where('assessment_no', $assessmentNo)->first();
        $pdfService = app(BatchBillPdfService::class);
        $record->qrImage = $pdfService->embedQrImageForPdf($record->qrImage ?? null, $bill?->id);

        $pdf = Pdf::loadView('pdf.bill-single', compact('record'))
            ->setPaper('A4', 'portrait')
            ->setOptions($pdfService->dompdfOptions());
        $output = $pdf->output();

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
        return app(BillPaymentQrService::class)->qrImageUrlForText($text, $size);
    }

    public function getPdf(mixed $receiptNo, $receipt): \Barryvdh\DomPDF\PDF
    {
        $receiptBase = rtrim((string) config('app.url'), '/');
        $qr = $this->generateQRCode("{$receiptBase}/receipt/{$receiptNo}");
        $data = [
            'payer_id' => $receipt->getBill->getPropertyList->owner_kgtin ?? '', // $receipt->kgtin ?? '',
            'paid_by' => $receipt->customer_name ?? '',
            'lga' => $receipt->getBill->getLGA->lga_name ?? '',
            'address' => $receipt->getBill->getPropertyList->property_address ?? '',
            'assessmentNo' => $receipt->assessment_no ?? '',
            'propertyId' => $receipt->building_code ?? '',
            'amount' => 'NGN'.number_format(round($receipt->amount) ?? 0),
            'amount_words' => $this->numberToWords(round($receipt->amount) ?? 0),
            'payment_purpose' => "Payment for Estate Dues (Revenue Code: 12020908) {$receipt->getBill->year}",
            'receipt_no' => $receipt->receipt_no ?? '',
            'invoice_no' => 'AST91574/34512/P1/2/2025',
            'swift_code' => $receipt->reference ?? '',
            'payment_date' => date('d/m/Y', strtotime($receipt->entry_date)),
            'agency' => 'LEKKI PENINSULA PHASE 1 RESIDENCE ASSOCIATION',
            'tax_station' => $receipt->ward ?? '',
            'pay_mode' => $receipt->pay_mode ?? '',
            'authorized_signatory' => 'Sule Saliu Enehe',
            'designation' => 'Executive Chairman',
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
            'user' => 'required',
        ], [
            'buildingCode.required' => 'Building code is required',
            'from.required' => 'Set a start date',
            'to.required' => 'Set an end date',
            'user.required' => 'User is required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        $buildingCode = $request->buildingCode;
        $from = $request->from;
        $to = $request->to;
        $user = User::find($request->user);
        if (empty($user)) {
            return response()->json(['errors' => 'No record found.'], 404);
        }
        $allowedLgaIds = $user->sectorLgaIds();
        $bills = BillPaymentLog::getCustomerStatementByKgtinDate($buildingCode, $from, $to, $allowedLgaIds);
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
        return app(BillPaymentQrService::class)->paymentUrlForRecord($record);
    }

    protected function applyBillTotals(object $record): void
    {
        if (! isset($record->bbf)) {
            $record->bbf = $this->balanceBroughtForward($record->year, $record->buildingCode);
        }
        $paidAmount = (float) ($record->paidAmount ?? 0);
        $currentYearDue = max(0.0, (float) $record->billAmount - $paidAmount);
        $record->totalPayable = max(0.0, (float) $record->bbf + $currentYearDue);
    }

    /**
     * Assessment payment QR for one bill row (single or batch PDF).
     *
     * @return string|null Temp file path to delete after PDF render, if any
     */
    protected function attachRoutePayQrToBillRecord(object $record, ?Billing $bill = null): ?string
    {
        return app(BatchBillPdfService::class)->attachRoutePayQrToBillRecord($record, $bill);
    }

    protected function cleanupQrTempPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
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

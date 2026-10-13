<?php

namespace App\Http\Controllers;

use App\Exports\BillDeliveryExport;
use App\Exports\BillingExport;
use App\Exports\BillPrintReportExport;
use App\Exports\CustomerReportExport;
use App\Exports\PaidBillExport;
use App\Exports\PaymentReportExport;
use App\Exports\PropertyExceptionExport;
use App\Exports\ReconciliationReportExport;
use App\Exports\SettlementReportExport;
use App\Exports\UnreconciledReportExport;
use App\Jobs\BulkExportBillByTypeJob;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\ExportRecord;
use App\Models\Lga;
use App\Models\Reconciliation;
use App\Models\ReconciliationMaster;
use App\Models\SettlementReportSetup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /*    public function exportExcel(Request $request)
        {


            $type = $request->type ;
            $userId = $request->user ;
            if($type == 'normal-outstanding'){
                return Excel::download(new PaidBillExport($userId,$type), 'billings.xlsx');
            }
            return Excel::download(new BillingExport($userId,$type), 'billings.xlsx');
        }*/
    public function exportExcel(Request $request)
    {
        $validator = Validator::make($request->route()->parameters() + $request->all(), [
            'user' => 'required|integer|exists:users,id',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->messages()], 422);
        }

        $type = (string) $request->type;
        $userId = (int) $request->user;

        $allowedTypes = [
            'special-outstanding', 'all-pending', 'pending-bills', 'returned-normal', 'returned-special',
            'paid-bills', 'si-paid-bills', 'normal-outstanding', 'partly-paid-bills',
        ];

        if (! in_array($type, $allowedTypes, true)) {
            return response()->json(['error' => 'Invalid export type.'], 400);
        }

        $fileName = 'billings_'.now()->format('Y_m_d_His').'.xlsx';
        $path = "exports/{$fileName}";

        $record = ExportRecord::create([
            'user_id' => $userId,
            'type' => $type,
            'file_path' => $path,
            'status' => 'queued',
            'message' => 'Export has been queued successfully.',
        ]);

        $queue = (string) config('billing.export_queue', 'default');

        BulkExportBillByTypeJob::dispatch($userId, $type, $path, $record->id)
            ->onQueue($queue);

        return response()->json([
            'message' => 'Export request queued successfully',
            'status' => 'queued',
            'export_id' => $record->id,
            'download_url' => url("/api/export-download/{$record->id}"),
        ], 202);
    }

    public function getExportStatus($id)
    {
        $record = ExportRecord::findOrFail($id);

        return response()->json([
            'status' => $record->status,
            'message' => $record->message,
            'export_id' => $record->id,
            'file_url' => $record->status === 'completed'
                ? url("/api/export-download/{$record->id}")
                : null,
        ]);
    }

    public function exportPaidBills(Request $request)
    {
        if (! $request->filled('type')) {
            $request->merge(['type' => 'paid-bills']);
        }

        return $this->exportExcel($request);
    }

    public function exportPartlyPaidBills(Request $request)
    {
        $request->merge(['type' => 'partly-paid-bills']);

        return $this->exportExcel($request);
    }

    public function exportPendingBills(Request $request)
    {
        $request->merge(['type' => 'all-pending']);

        return $this->exportExcel($request);
    }

    public function exportPropertyException(Request $request)
    {
        return Excel::download(new PropertyExceptionExport, 'property-exceptions.xlsx');
    }

    public function exportCustomerReport(Request $request)
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
        $formattedData = [];
        foreach ($bills as $index => $billing) {
            $bill = Billing::find($billing->bill_master);
            $narration = 'N/A';
            if (! empty($bill)) {
                $pDate = date('d M, Y', strtotime($billing->entry_date));
                $narration = "LUC: {$bill->bill_amount}, Payment Date: {$pDate}, BUEAREU OF LANDS, Mode of Payment: {$billing->pay_mode},
            Assessment No.: {$bill->assessment_no}, Transaction Reference: {$bill->trans_ref}, Year: {$bill->year}";
            }
            $formattedData[] = [
                '#' => $index + 1,
                'DATE' => date('d M, Y', strtotime($billing->entry_date)),
                '(₦)AMOUNT' => number_format($billing->amount, 2),
                'CHANNEL' => $billing->pay_mode,
                'RECEIPT' => $billing->receipt_no,
                'NARRATION' => $narration,
            ];
        }

        return Excel::download(new CustomerReportExport($formattedData, $buildingCode, $from, $to), 'customer-report.xlsx');
    }

    public function exportBillDeliveryReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lga' => 'required',
            'from' => 'required',
            'to' => 'required',
        ], [
            'lga.required' => 'LGA is required',
            'from.required' => 'Set a start date',
            'to.required' => 'Set an end date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        $lga = $request->lga;
        $from = $request->from;
        $to = $request->to;
        $bills = Billing::getBillDeliveryReportNoPagination($lga, $from, $to);
        $formattedData = [];
        foreach ($bills as $index => $billing) {
            $formattedData[] = [
                '#' => $index + 1,
                'DATE' => date('d M, Y', strtotime($billing->entry_date)),
                'ASSESSMENT NO.' => $billing->assessment_no,
                'BUILDING CODE' => $billing->building_code,
                'YEAR' => $billing->year,
                'BILLING CODE' => $billing->pav_code,
                'ZONE' => $billing->zone_name ?? '',
                'CATEGORY' => $billing->getPropertyClassification->class_name ?? '',
                'PROPERTY USE' => $billing->property_use,
                'OCCUPANCY' => $billing->property_use ?? '',
                'PROPERTY NAME' => $billing->getPropertyList->property_name ?? '',
                'CHARGE RATE' => $billing->cr ?? '',
                'ASSESSED MKT. VALUE' => number_format($billing->assessed_value, 2),
                '(₦)LUC' => number_format($billing->bill_amount, 2),
            ];
        }

        return Excel::download(new BillDeliveryExport($formattedData, $lga, $from, $to), 'Bill-Delivery-report.xlsx');
    }

    public function exportBillPrintReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lga' => 'required',
            'from' => 'required',
            'to' => 'required',
        ], [
            'lga.required' => 'LGA is required',
            'from.required' => 'Set a start date',
            'to.required' => 'Set an end date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }

        $lga = $request->lga;
        $from = $request->from;
        $to = $request->to;
        $bills = Billing::getBillPrintReportNoPagination($lga, $from, $to);
        $formattedData = [];
        foreach ($bills as $index => $billing) {
            $formattedData[] = [
                '#' => $index + 1,
                'DATE' => date('d M, Y', strtotime($billing->entry_date)),
                'ASSESSMENT NO.' => $billing->assessment_no,
                'BUILDING CODE' => $billing->building_code,
                'YEAR' => $billing->year,
                'BILLING CODE' => $billing->pav_code,
                'ZONE' => $billing->zone_name ?? '',
                'CATEGORY' => $billing->getPropertyClassification->class_name ?? '',
                'PROPERTY USE' => $billing->property_use,
                'OCCUPANCY' => $billing->property_use ?? '',
                'PROPERTY NAME' => $billing->getPropertyList->property_name ?? '',
                'CHARGE RATE' => $billing->cr ?? '',
                'ASSESSED MKT. VALUE' => number_format($billing->assessed_value, 2),
                '(₦)LUC' => number_format($billing->bill_amount, 2),
            ];
        }

        return Excel::download(new BillPrintReportExport($formattedData, $lga, $from, $to), 'Bill-Print-report.xlsx');
    }

    public function exportPaymentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required',
            'type' => 'required',
            'from' => 'required',
            'to' => 'required',
            'user' => 'required',
        ], [
            'keyword.required' => 'Something is missing',
            'from.required' => 'Set a start date',
            'to.required' => 'Set an end date',
            'type.required' => 'Indicate type',
            'user.required' => 'User is required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $from = $request->from;
        $to = $request->to;
        $type = $request->type;
        $keyword = $request->keyword; // LGA ID ||
        $user = User::find($request->user);
        if (empty($user)) {
            return response()->json(['errors' => 'No record found.'], 404);
        }
        $allowedLgaIds = $user->sectorLgaIds();
        switch ($type) {
            case 'lga':
                $keyword = (int) $keyword;
                if (! empty($allowedLgaIds) && $keyword > 0 && ! in_array($keyword, $allowedLgaIds, true)) {
                    return response()->json(['message' => 'Unauthorized LGA'], 403);
                }
                $bills = BillPaymentLog::getPaymentReportByLGADateRange($keyword, $from, $to, $allowedLgaIds);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no,
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }

                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
            case 'ward':
                $bills = BillPaymentLog::getPaymentReportByWardDateRange($keyword, $from, $to, $allowedLgaIds);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    // $lga = Lga::find($);
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no ?? '',
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }

                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
            case 'zone':
                $bills = BillPaymentLog::getPaymentReportByZoneDateRange($keyword, $from, $to, $allowedLgaIds);
                $formattedData = [];
                foreach ($bills as $index => $billing) {
                    $formattedData[] = [
                        '#' => $index + 1,
                        'DATE' => date('d/m/Y', strtotime($billing->entry_date)),
                        'BUILDING CODE' => $billing->building_code ?? '',
                        'ASSESSMENT NO' => $billing->assessment_no,
                        'RECEIPT NO.' => $billing->receipt_no,
                        'BANK NAME' => $billing->bank_name ?? '',
                        'TRANS. REF' => $billing->trans_ref,
                        'OWNER NAME' => $billing->customer_name,
                        'LGA' => $billing->lga->lga_name ?? '',
                        '(NGN)AMOUNT' => $billing->amount ?? 0,
                    ];
                }

                return Excel::download(new PaymentReportExport($formattedData, $from, $to, $type, $keyword), 'payment-report.xlsx');
        }
    }

    public function exportSettlementReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date',
            'user' => 'required',
        ], [
            'from.required' => 'Choose start date',
            'to.required' => 'Choose end date',
            'user.required' => 'User is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
                'message' => 'Validation error',
                'detail' => 'All fields are required.',
            ], 422);
        }
        $from = $request->from;
        $to = $request->to;
        $user = User::find($request->user);
        if (empty($user)) {
            return response()->json(['errors' => 'No record found.'], 404);
        }
        $allowedLgaIds = $user->sectorLgaIds();
        $data = DB::table('bill_payment_logs')
            ->join('lgas', 'lgas.id', '=', 'bill_payment_logs.lga_id')
            ->orderBy('lgas.id', 'ASC')
            ->whereBetween('entry_date', [$from, $to])
            ->when(! empty($allowedLgaIds), fn ($q) => $q->whereIn('bill_payment_logs.lga_id', $allowedLgaIds))
            ->select('entry_date as date', 'building_code as buildingCode', 'assessment_no as assessmentNo', 'amount', 'lgas.lga_name as lgaName')
            ->get();
        $setup = SettlementReportSetup::first();
        $newWaves = isset($setup->newwaves) ? $setup->newwaves : 0;
        $lga = isset($setup->lga) ? $setup->lga : 0;
        $kgirs = isset($setup->kgirs) ? $setup->kgirs : 0;
        $lgaList = Lga::fetchAllLGAs();

        return Excel::download(new SettlementReportExport($data, $lgaList, $kgirs, $lga, $newWaves, $from, $to), 'settlement_report.xlsx');
    }

    public function exportReconciliationReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uuid' => 'required',
        ], [
            'uuid.required' => 'Field is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
                'message' => 'Validation error',
                'detail' => 'All fields are required.',
            ], 422);
        }
        $uuid = $request->uuid;
        $record = ReconciliationMaster::where('uuid', $uuid)->first();
        if (empty($record)) {
            return response()->json([
                'errors' => 'Whoops!',
                'message' => 'Something went wrong.',
                'detail' => 'No record found.',
            ], 422);
        }
        $details = Reconciliation::where('master_id', $record->id)->get();

        return Excel::download(new ReconciliationReportExport($details), 'reconciliation_report.xlsx');
    }

    public function exportUnreconciledReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required',
            'year' => 'required',
        ], [
            'month.required' => 'Field is required',
            'year.required' => 'Field is required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
                'message' => 'Validation error',
                'detail' => 'All fields are required.',
            ], 422);
        }
        $month = $request->month;
        $year = $request->year;
        $records = Reconciliation::where('month', $month)
            ->where('year', $year)
            ->where('reconciled', 0)
            ->get();

        return Excel::download(new UnreconciledReportExport($records), 'unreconciled_report.xlsx');
    }
}

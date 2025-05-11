<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillDetailResource;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\PrintBillLog;
use App\Models\PropertyList;
use App\Traits\UtilityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Spatie\LaravelPdf\Facades\Pdf as SpatiPDF;
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
                'property_classifications.class_name as className'
            ])
            ->whereIn('billings.id', $printLogIds)
            ->get();
        $pdf = Pdf::loadView('pdf.bill',compact('records'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);
    }


    public function generatePDFByAssessmentNo(Request $request)
    {

        $assessmentNo = $request->assessmentNo ?? '';
        $record =  DB::table('billings')
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
                'property_classifications.class_name as className'
            ])
            ->where('billings.assessment_no', $assessmentNo)
            ->first();
        if(empty($record)){
            return \response()->json(['error'=>'No record found'],404);
        }
        $pdf = Pdf::loadView('pdf.bill-single',compact('record'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Bill.pdf"',
        ]);
    }


    public function showReceipt(Request $request){
        $receiptNo = $request->receipt;
        if(!isset($receiptNo)){
            return \response()->json(['error'=>'Receipt number is required'],422);
        }
        $receipt = BillPaymentLog::where('receipt_no', $receiptNo)->first();
        if(empty($receipt)){
            return "Invalid Receipt No.";
        }
        $pdf = $this->getPdf($receiptNo, $receipt);
        return $pdf->stream($receipt->receipt_no.'.pdf');
    }

    public function downloadReceipt(Request $request){
        $receiptNo = $request->receipt;
        if(!isset($receiptNo)){
            return \response()->json(['error'=>'Receipt number is required'],422);
        }
        $receipt = BillPaymentLog::where('receipt_no', $receiptNo)->first();
        if(empty($receipt)){
            return \response()->json(['error'=>"No record found"],404);
        }
        $pdf = $this->getPdf($receiptNo, $receipt);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Receipt.pdf"',
        ]);
    }



    function generateQRCode($text, $size = 150) {
        $encodedText = urlencode($text);
        return "https://quickchart.io/qr?text={$encodedText}&size={$size}";
    }

    /**
     * @param mixed $receiptNo
     * @param $receipt
     * @return \Barryvdh\DomPDF\PDF
     */
    public function getPdf(mixed $receiptNo, $receipt): \Barryvdh\DomPDF\PDF
    {
        $qr = $this->generateQRCode("http://api.kslas.ng/receipt/{$receiptNo}");
        $data = [
            'payer_id' => $receipt->getBill->getPropertyList->owner_kgtin ?? '', //$receipt->kgtin ?? '',
            'paid_by' => $receipt->customer_name ?? '',
            'address' => $receipt->getBill->getPropertyList->property_address ?? '',
            'assessmentNo' => $receipt->assessment_no ?? '',
            'propertyId' => $receipt->building_code ?? '',
            'amount' => 'NGN' . number_format($receipt->amount ?? 0),
            'amount_words' => $this->numberToWords($receipt->amount ?? 0),
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
                'defaultFont' => 'Arial'
            ]);
        return $pdf;
    }


    public function showCustomerStatementReportPDF(){
        return view('pdf.customer-report');
    }


    public function generateCustomerStatementReportPDF(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'buildingCode'=>'required',
            'from'=>'required',
            'to'=>'required'
        ],[
            'buildingCode.required'=>"Building code is required" ,
            'from.required'=>"Set a start date" ,
            'to.required'=>"Set an end date"
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        $buildingCode = $request->buildingCode ;
        $from = $request->from ;
        $to = $request->to ;
        $bills =  BillPaymentLog::getCustomerStatementByKgtinDate($buildingCode, $from, $to);
        $records = [];

        foreach ($bills as $index => $billing) {
            $bill = Billing::find($billing->bill_master);
            $narration = "N/A";
            if(!empty($bill)){
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
        $headerInfo['payerId'] = !empty($property) ? $property->owner_kgtin : '';
        $headerInfo['address'] = !empty($property) ? $property->property_address : '';
        $pdf = Pdf::loadView('pdf.customer-report',compact('records', 'headerInfo'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);

        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);
    }


}

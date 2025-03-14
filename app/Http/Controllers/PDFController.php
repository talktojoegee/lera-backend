<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillDetailResource;
use App\Models\Billing;
use App\Models\PrintBillLog;
use App\Traits\UtilityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Spatie\LaravelPdf\Facades\Pdf as SpatiPDF;
class PDFController extends Controller
{
    use UtilityTrait;
    public function generatePdf(Request $request)
    {
        set_time_limit(120);
        ini_set('memory_limit', '256M');
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $data = $request->all();
        $pdf = Pdf::loadView('pdf.table', compact('data'))
            ->setPaper('a4', 'portrait');
        return response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]);

    }


    public function generateDomPdf(Request $request)
    {
        $batchCode = $request->batchCode ?? '';
        $printLogIds = PrintBillLog::where('batch_code', $batchCode)->pluck('bill_id')->toArray();
       $records =  DB::table('billings')
            ->join('lgas', 'billings.lga_id', '=', 'lgas.id')
            ->join('property_lists', 'property_lists.id', '=', 'billings.property_id')
            ->join('property_classifications', 'property_classifications.id', '=', 'billings.class_id')
           ->whereIn('billings.id', $printLogIds)
            ->get();
        $pdf = Pdf::loadView('pdf.table',compact('records'))
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
       $records =  DB::table('billings')
            ->join('lgas', 'billings.lga_id', '=', 'lgas.id')
            ->join('property_lists', 'property_lists.id', '=', 'billings.property_id')
            ->join('property_classifications', 'property_classifications.id', '=', 'billings.class_id')
           ->where('billings.assessment_no', $assessmentNo)
            ->first();
        $pdf = Pdf::loadView('pdf.table',compact('records'))
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

    public function amountInWordsHelper($amount){
        return $this->amountInWordsHelper($amount);
    }


}

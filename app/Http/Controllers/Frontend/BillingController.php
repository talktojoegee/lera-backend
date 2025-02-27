<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use Illuminate\Http\Request;

class BillingController extends Controller
{



    public function handleBillPaymentRequest(Request $request){
        $assessmentNo = $request->assessmentNo;
        $bill = Billing::where('assessment_no', $assessmentNo)->first();
        if(empty($bill)){
            return response()->json(['data'=>"No record found."], 404);
        }
        return response()->json(['data'=>$bill],200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\BillingRecordResource;
use App\Http\Resources\DashboardStatisticsResource;
use App\Http\Resources\OutstandingBillResource;
use App\Http\Resources\PaidBillResource;
use App\Http\Resources\RetrieveBillResource;
use App\Models\Billing;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    public function __construct(){

    }

    public function retrieveBills(Request $request){
        $validator = Validator::make($request->all(),[
            //"lgaId"=>"required",
            "year"=>"required",
            "billedBy"=>"required",
        ],[
            //"lgaId.required"=>"LGA value is required",
            "year.required"=>"Year field is required",
            "billedBy.required"=>"",
        ]);
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }

        return new RetrieveBillResource($request);

    }


    public function processBill(Request $request){
        $validator = Validator::make($request->all(),[
            "lgaId"=>"required",
            "year"=>"required",
            //"billedBy"=>"required",
        ],[
            "lgaId.required"=>"LGA value is required",
            "year.required"=>"Year field is required",
            //"billedBy.required"=>"",
        ]);
        if($validator->fails() ){
            return ApiResponse::error($validator->messages(),422);

        }

        $currentYear = date('Y');
        if($request->year > $currentYear){
            return ApiResponse::error("Whoops! You're trying to be faster than your shadow. Calm down :) You can't process bill ahead.",400);
        }


        $propertyLists = PropertyList::where('lga_id', $request->lgaId)/*->take(10)*/->get();
        if (empty($propertyLists)) {
            return ApiResponse::error("Whoops! There is nothing to process",400);
        }
        // Check if a bill for the specified year and LGA already exists
        $existingBills = Billing::where('lga_id', $request->lgaId)->where('year', $request->year)->get();
        if (count($existingBills) > 0) {
            return ApiResponse::error("Whoops! Bill for the specified year and LGA has already been processed.",400);
        }


        foreach ($propertyLists as $list) {

            $pavOptional = PropertyAssessmentValue::where("pav_code", $list->pav_code)->first();
         /*   $pavCode = null;
           if(){

           }*/

           /*
            * Tie Government = State government, religious = commercial, recreational = commercial
            * Religious =
            * Residential =
            * Commercial =
            * Tie Owner to = Owner occupied; tenant to = 3rd Party Only
            */
            $lga = Lga::find($list->lga_id);

                  if (!empty($pavOptional) && !empty($lga)) {
                    $uniqueNumber = uniqid();
                    $billAmount = ($pavOptional->value_rate/100) * $pavOptional->assessed_amount;
                    $billing = new Billing();
                    $billing->building_code = $list->building_code ?? null;
                    $billing->assessment_no = $uniqueNumber;
                    $billing->assessed_value = $pavOptional->assessed_amount ?? 0;
                    $billing->bill_amount = $billAmount ?? 0;
                    $billing->year = $request->year;
                    $billing->entry_date = now();
                    $billing->billed_by = 1;
                    $billing->paid = 0 ;
                    $billing->paid_amount = 0.00;
                    $billing->objection = 0;
                    $billing->lga_id = $request->lgaId;
                    $billing->property_id = $list->id;
                    $billing->bill_rate = $pavOptional->value_rate ?? 0;
                    $billing->pav_code = $pavOptional->pav_code;
                    $billing->zone_name = $list->zone_name ?? '';
                    $billing->url = substr(sha1(time()),29,40);
                    $billing->save();
                }

          }
        return BillingRecordResource::collection($this->_fetchProcessedBills($request->year, $request->lgaId));

    }

    private function _fetchProcessedBills($year, $lgaId){
        return Billing::where("year", $year)->where("lga_id", $lgaId)->get();
    }

    public function showBillDataOnDashboard(){

        return response()->json($this->getMonthlyBillPaymentForCurrentYear());
    }


    public function showDashboardStatistics(Request $request){
        return new DashboardStatisticsResource($request);
    }


    public function showPropertyDistributionByZones(){

        return response()->json($this->getPropertyDistributionByZones());
    }




    public function showPropertyDistributionByLGA(){

        return response()->json($this->getPropertyDistributionByLGA());
    }



    public function showOutstandingBills(){

        return OutstandingBillResource::collection(Billing::getOutstandingBills());
    }


    public function showPaidBills(){

        return PaidBillResource::collection(Billing::getPaidBills());
    }




    public function showBillDetails($url){
        $billDetail = Billing::where('url',$url)->first();

        if (!$billDetail) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }

        return new BillDetailResource($billDetail);
    }







    private function getMonthlyBillPaymentForCurrentYear(){
        $billsData = DB::table('billings')
            ->selectRaw('MONTH(created_at) AS month, SUM(bill_amount) AS totalBillAmount')
            ->whereYear('created_at', '=', date('Y')) // Filter for the current year
            ->groupBy('month')
            ->orderBy('month')
            ->get();
                $chartData = [
                    'labels' => [
                        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                    ],
                    'datasets' => [
                        [
                            'label' => 'Bill payment',
                            'data' => array_fill(0, 12, 0),
                            'backgroundColor' => [
                                '#FF5733', '#33FF57', '#3357FF', '#FF33A6', '#33FFF0', '#F0FF33',
                                '#A633FF', '#FF8C33', '#33A6FF', '#8CFF33', '#FF3333', '#33FF8C'
                            ]
                        ]
                    ]
                ];

                foreach ($billsData as $bill) {
                    $chartData['datasets'][0]['data'][$bill->month - 1] = $bill->totalBillAmount;
                }
                return $chartData;
    }



    private function getCurrentYearBillByMonthAndLGA(){
        $billsData = DB::table('billings')
            ->selectRaw('MONTH(created_at) AS month, lga_id, SUM(bill_amount) AS totalBillAmount')
            ->whereYear('created_at', '=', date('Y')) // Filter for the current year
            ->groupBy('month', 'lga_id')
            ->orderBy('month')
            ->orderBy('lga_id')
            ->get();

        // Initialize Chart.js-compatible structure
        $chartData = [
            'labels' => [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ],
            'datasets' => []
        ];

        // Group bills by `lga_id` and prepare datasets
        $groupedBills = $billsData->groupBy('lga_id');
        foreach ($groupedBills as $lgaId => $data) {
            $dataset = [
                'label' => 'LGA ' . $lgaId,
                'data' => array_fill(0, 12, 0), // Initialize all months with 0
                'backgroundColor' => '#' . substr(md5($lgaId), 0, 6), // Generate unique color
            ];

            foreach ($data as $bill) {
                $dataset['data'][$bill->month - 1] = $bill->totalBillAmount;
            }

            $chartData['datasets'][] = $dataset;
        }

        return $chartData;
    }


    private function getBillsByZones(){
        $billsData = DB::table('billings')
            ->join('zones', 'billings.zone_id', '=', 'zones.id')
            ->selectRaw('MONTH(billings.created_at) AS month, zones.name AS zoneName, SUM(billings.bill_amount) AS totalBillAmount')
            ->whereYear('billings.created_at', '=', date('Y')) // Filter for the current year
            ->groupBy('month', 'zoneName')
            ->orderBy('month')
            ->orderBy('zoneName')
            ->get();

        // Initialize Chart.js-compatible structure
        $chartData = [
            'labels' => [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ],
            'datasets' => []
        ];

        // Group bills by `zoneName` and prepare datasets
        $groupedBills = $billsData->groupBy('zoneName');
        foreach ($groupedBills as $zoneName => $data) {
            $dataset = [
                'label' => 'Zone ' . $zoneName,
                'data' => array_fill(0, 12, 0), // Initialize all months with 0
                'backgroundColor' => '#' . substr(md5($zoneName), 0, 6), // Generate unique color
            ];

            foreach ($data as $bill) {
                $dataset['data'][$bill->month - 1] = $bill->totalBillAmount;
            }

            $chartData['datasets'][] = $dataset;
        }
    }


    private function getPropertyDistributionByZones(){

        $propertyData = DB::table('property_lists')
            ->join('zones', 'property_lists.zone_name', '=', 'zones.zone_name')
            ->selectRaw('MONTH(property_lists.created_at) AS month, zones.zone_name AS zoneName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', date('Y') ) // Filter for the current year
            ->groupBy('month', 'zoneName')
            ->orderBy('month')
            ->orderBy('zoneName')
            ->get();

        // Initialize Chart.js-compatible structure
        $chartData = [
            'labels' => [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ],
            'datasets' => []
        ];

        // Group property listings by `zoneName` and prepare datasets
        $groupedProperties = $propertyData->groupBy('zoneName');
        foreach ($groupedProperties as $zoneName => $data) {
            $dataset = [
                'label' => 'Zone ' . $zoneName,
                'data' => array_fill(0, 12, 0), // Initialize all months with 0
                'backgroundColor' => '#' . substr(md5($zoneName), 0, 6), // Generate unique color
            ];

            foreach ($data as $property) {
                $dataset['data'][$property->month - 1] = $property->totalProperties;
            }

            $chartData['datasets'][] = $dataset;
        }
        return $chartData;

    }


    private function getPropertyDistributionByLGA(){

        $propertyData = DB::table('property_lists')
            ->join('lgas', 'property_lists.lga_id', '=', 'lgas.id')
            ->selectRaw('MONTH(property_lists.created_at) AS month, lgas.lga_name AS lgaName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', date('Y')) // Filter for the current year
            ->groupBy('month', 'lgaName')
            ->orderBy('month')
            ->orderBy('lgaName')
            ->get();

        // Initialize Chart.js-compatible structure
        $chartData = [
            'labels' => [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ],
            'datasets' => []
        ];

        // Group property listings by `zoneName` and prepare datasets
        $groupedProperties = $propertyData->groupBy('lgaName');
        foreach ($groupedProperties as $lgaName => $data) {
            $dataset = [
                'label' =>  $lgaName,
                'data' => array_fill(0, 12, 0), // Initialize all months with 0
                'backgroundColor' => '#' . substr(md5($lgaName), 0, 6), // Generate unique color
            ];

            foreach ($data as $property) {
                $dataset['data'][$property->month - 1] = $property->totalProperties;
            }

            $chartData['datasets'][] = $dataset;
        }
        return $chartData;

    }


    public function chartTest(){
        return response()->json([
            'billAmount'=>Billing::getCurrentYearMonthlyBillAmount(),
            'amountPaid'=>Billing::getCurrentYearMonthlyAmountPaid(),
            'byZones'=>Billing::getCurrentYearBillsByZone(),
            ]);
    }



}

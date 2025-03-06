<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\BillDetailResource;
use App\Http\Resources\BillingRecordResource;
use App\Http\Resources\BillSearchResource;
use App\Http\Resources\DashboardStatisticsResource;
use App\Http\Resources\LGAChairDashboardStatisticsResource;
use App\Http\Resources\OutstandingBillResource;
use App\Http\Resources\PaidBillResource;
use App\Http\Resources\RetrieveBillResource;
use App\Jobs\ProcessBillingJob;
use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\EditBillLog;
use App\Models\Lga;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function test(){
        echo "Test value.";
    }


    public function processBill(Request $request)
    {

        $year = $request->year;
        $lgaId = $request->lgaId;
        $billedBy = $request->billedBy;

        $validator = Validator::make($request->all(), [
            "lgaId" => "required",
            "year" => "required",
            "billedBy"=>"required",
        ], [
            "lgaId.required" => "LGA value is required",
            "year.required" => "Year field is required",
            "billedBy.required"=>"",
        ]);
        if ($validator->fails()) {
            return ApiResponse::error($validator->messages(), 422);

        }

        $currentYear = date('Y');
        if ($year > $currentYear) {
            return ApiResponse::error("Whoops! You can't process bill ahead.", 400);
        }
        $propertyLists = [];
        if ($lgaId == 0) { //All locations/LGAs
            $propertyLists = PropertyList::orderBy('id', 'DESC')->get();
        } else {
            $propertyLists = PropertyList::where('lga_id', $lgaId)/*->take(10)*/ ->get();
        }


        if (empty($propertyLists)) {
            return ApiResponse::error("Whoops! There is nothing to process", 400);
        }
        ProcessBillingJob::dispatch($lgaId, $year, $billedBy);
        //return response()->json(['data'=>$counter],200);
        return response()->json(['data'=>"Bill queued for processing!"], 201);  // BillingRecordResource::collection($this->_fetchProcessedBills($request->year, $request->lgaId));

    }

    private function _fetchProcessedBills($year, $lgaId){
        return Billing::where("year", $year)->where("lga_id", $lgaId)->get();
    }

    public function showBillDataOnDashboard(Request $request){

        return response()->json($this->getMonthlyBillPaymentByYear($request->year));
    }


    public function showDashboardStatistics(Request $request){
        return new DashboardStatisticsResource($request);
    }


    public function showPropertyDistributionByZones(Request $request){

        return response()->json(
            [
                'zone'=>$this->getPropertyDistributionByZones($request->year),
                'lga'=>$this->getPropertyDistributionByLGA($request->year)
            ]
        );
    }




    public function showPropertyDistributionByLGA(){

        return response()->json($this->getPropertyDistributionByLGA());
    }



    public function showOutstandingBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getBills($limit, $skip, 0, 0, 4, $propertyUse, [0])),
            'total'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0])->count(),
            'grossBills'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0])->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0])->sum('paid_amount'),
            'balanceAmount'=>(Billing::getBillsByParams(0,0,4, $propertyUse, [0])->sum('bill_amount') - Billing::getBillsByParams(0,0,3, $propertyUse, [0])->sum('paid_amount')),
        ],200);
    }


    public function showLGAChairOutstandingBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getLGAChairBills($limit, $skip, 0, 4,  $user->lga)),
            'total'=>Billing::getLGAChairBillsByParams(0,4,$user->lga)->count(),
            'grossBills'=>Billing::getLGAChairBillsByParams(0,4,$user->lga)->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getLGAChairBillsByParams(0,4,$user->lga)->sum('paid_amount'),
            'balanceAmount'=>(Billing::getLGAChairBillsByParams(0,4,$user->lga)->sum('bill_amount') - Billing::getLGAChairBillsByParams(0,4,$user->lga)->sum('paid_amount')),
        ],200);
    }
    public function showLGAChairBillPayment(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getLGAChairBills($limit, $skip, 1, 4,  $user->lga)),
            'total'=>Billing::getLGAChairBillsByParams(1,4,$user->lga)->count(),
            'grossBills'=>Billing::getLGAChairBillsByParams(1,4,$user->lga)->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getLGAChairBillsByParams(1,4,$user->lga)->sum('paid_amount'),
            'balanceAmount'=>(Billing::getLGAChairBillsByParams(1,4,$user->lga)->sum('bill_amount') - Billing::getLGAChairBillsByParams(1,4,$user->lga)->sum('paid_amount')),
        ],200);
    }


    public function showAllOutstandingBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getBills($limit, $skip, 0, 0, 4, $propertyUse, [0,1])),
            'total'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0,1])->count(),
            'grossBills'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0,1])->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getBillsByParams(0,0,4, $propertyUse, [0,1])->sum('paid_amount'),
            'balanceAmount'=>(Billing::getBillsByParams(0,0,4, $propertyUse, [0,1])->sum('bill_amount') - Billing::getBillsByParams(0,0,4, $propertyUse, [0,1])->sum('paid_amount')),
        ],200);
    }

    public function showOutstandingSpecialInterestBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getBills($limit, $skip, 0, 0, 4, $propertyUse, [1])),
            'total'=>Billing::getBillsByParams(0,0,4, $propertyUse, [1])->count(),
            'grossBills'=>Billing::getBillsByParams(0,0,4, $propertyUse, [1])->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getBillsByParams(0,0,4, $propertyUse, [1])->sum('paid_amount'),
            'balanceAmount'=>(Billing::getBillsByParams(0,0,4, $propertyUse, [1])->sum('bill_amount') - Billing::getBillsByParams(0,0,4, $propertyUse, [1])->sum('paid_amount')),
        ],200);
    }

    public function showBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $status = $request->status ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getBillsByStatus($limit, $skip, $status, $propertyUse)),
            'total'=>Billing::getBillsByParamsByStatus($status, $propertyUse)->count(),
        ],200);
    }
    public function showAllPendingBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);

        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getAllPendingBillsByStatus($limit, $skip, $propertyUse)),
            'total'=>Billing::getAllPendingBillsByParamsByStatus($propertyUse)->count(),
        ],200);
    }
    public function showSpecialInterestBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $status = $request->status ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getSpecialInterestBillsByStatus($limit, $skip, $status, $propertyUse)),
            'total'=>Billing::getSpecialInterestBillsByParamsByStatus($status, $propertyUse)->count(),
        ],200);
    }


    public function showReturnedBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getAllReturnedBills($limit, $skip, $propertyUse)),
            'total'=>Billing::getAllReturnedBillsByParams($propertyUse)->count(),
        ],200);
    }

    public function showSpecialInterestReturnedBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getAllSpecialInterestReturnedBills($limit, $skip,$propertyUse)),
            'total'=>Billing::getAllSpecialInterestReturnedBillsByParams($propertyUse)->count(),
        ],200);
    }


    public function showPaidBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>PaidBillResource::collection(Billing::getAllPaidBills($limit, $skip, 1, 0, 4, $propertyUse)),
            'total'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->count(),
            'grossBills'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('paid_amount'),
            'balanceAmount'=>(Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('bill_amount') - Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('paid_amount')),
        ]);
    }

    public function showPaidSpecialInterestBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $userId = $request->user ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>PaidBillResource::collection(Billing::getAllPaidSpecialInterestBills($limit, $skip, 1, 0, 4, $propertyUse)),
            'total'=>Billing::getAllSpecialInterestBillsByParams(1,0,4, $propertyUse)->count(),
            'grossBills'=>Billing::getAllSpecialInterestBillsByParams(1,0,4, $propertyUse)->sum('bill_amount'),
            'grossAmountPaid'=>Billing::getAllSpecialInterestBillsByParams(1,0,4, $propertyUse)->sum('paid_amount'),
            'balanceAmount'=>(Billing::getAllSpecialInterestBillsByParams(1,0,4, $propertyUse)->sum('bill_amount') - Billing::getAllSpecialInterestBillsByParams(1,0,4, $propertyUse)->sum('paid_amount')),
        ]);
    }




    public function showBillDetails($url){
        $billDetail = Billing::where('url',$url)->first();
        //$billDetail = Billing::where('url',$url)->first();

        if (!$billDetail) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }

        return new BillDetailResource($billDetail);
    }



    public function updateBillChanges(Request $request){

        $validator = Validator::make($request->all(),
            [
                "billId"=>"required",
                "actionedBy"=>"required",
                "lucAmount"=>"required",
                "chargeRate"=>"required",
                "la"=>"required",
                "ba"=>"required",
                "rr"=>"required",
                "dr"=>"required",
                "br"=>"required",
                "lr"=>"required",
            ],
            [
                "billId.required"=>"Whoops! Something is missing",
                "actionedBy.required"=>"Who action this objection?",
                "lucAmount.required"=>"Enter amount",
                "chargeRate.required"=>"Enter rate",
                "assessedValue.required"=>"Enter assess value",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = Billing::find( $request->billId);
        if (!$record) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }
        $code = $record->pav_code;
        $record->assessed_value = $request->assessedValue;
        $record->bill_amount = $request->lucAmount;
        //$record->bill_rate = $request->chargeRate;
        $record->returned = 2; //processed
        $record->status = 0; //take it back to pending for it to re-enter the workflow process
        $record->la = $request->la ?? 0;
        $record->ba = $request->ba ?? 0;
        $record->rr = $request->rr ?? 0;
        $record->dr = $request->dr ?? 0;
        $record->br = $request->br ?? 0;
        $record->lr = $request->lr ?? 0;
        $record->pav_code = str_replace("B", "CS", $code);
                    //log edit bill changes
                    $log = new EditBillLog();
                    $log->bill_id = $record->id ?? '';
                    $log->edited_by = $request->actionedBy ?? '';
                    $log->building_code = $record->building_code ?? '';
                    $log->prev_la = $record->la ?? 0;
                    $log->prev_ba = $record->ba ?? 0;
                    $log->prev_rr = $record->rr ?? 0;
                    $log->prev_dr = $record->dr ?? 0;
                    $log->prev_br = $record->br ?? 0;
                    $log->prev_lr = $record->lr ?? 0;
                    $log->prev_luc = $record->bill_amount ?? 0;
                    $log->prev_assess_value = $record->assessed_value;

                    $log->cur_la = $request->la;
                    $log->cur_ba = $request->ba;
                    $log->cur_rr = $request->rr;
                    $log->cur_dr = $request->dr;
                    $log->cur_br = $request->br;
                    $log->cur_lr = $request->lr;
                    $log->cur_luc = $request->lucAmount;
                    $log->cur_assess_value = $request->assessedValue;
                    $log->save();

        $record->save();

        //$this->sendEmailHandler($record, $bill, $request->action);
        return response()->json(['message' => 'Success! Action successful.'], 200);
    }







    private function getMonthlyBillPaymentByYear($year){
        $billsData = DB::table('billings')
            ->selectRaw('MONTH(created_at) AS month, SUM(paid_amount) AS totalBillAmount')
            ->where('status', 4)
            ->whereYear('entry_date', '=', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $chartData = ['labels' => [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'datasets' => [
                [
                    'data' => array_fill(0, 12, 0),
                ]
            ]
        ];

        foreach ($billsData as $bill) {
            $chartData['datasets'][0]['data'][$bill->month - 1] = $bill->totalBillAmount;
        }
        return $chartData;
    }


    private function getLGAChairMonthlyBillPaymentByYear($year, $lgaId){
        $billsData = DB::table('billings')
            ->selectRaw('MONTH(created_at) AS month, SUM(paid_amount) AS totalBillAmount')
            ->whereYear('entry_date', '=', $year)
            ->where('lga_id', $lgaId)
            ->where('status', 4)//approved
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $chartData = ['labels' => [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'datasets' => [
                [
                    'data' => array_fill(0, 12, 0),
                ]
            ]
        ];

        foreach ($billsData as $bill) {
            $chartData['datasets'][0]['data'][$bill->month - 1] = $bill->totalBillAmount;
        }
        return $chartData;
    }

    /*
    private function getMonthlyBillPaymentByYear($year){
        $billsData = DB::table('billings')
            ->selectRaw('MONTH(created_at) AS month, SUM(bill_amount) AS totalBillAmount')
            ->whereYear('created_at', '=', $year) // Filter for the chosen year
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
    }*/



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


    private function getPropertyDistributionByZones($year){

        return DB::table('property_lists')
            ->join('zones', 'property_lists.sub_zone', '=', 'zones.sub_zone')
            ->selectRaw('zones.sub_zone AS zoneName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', $year )
            ->groupBy('zoneName')
            //->orderBy('month')
            ->orderBy('zoneName')
            ->get();

    }

    private function getPropertyDistributionByLGA($year){

        return DB::table('property_lists')
            ->join('lgas', 'property_lists.lga_id', '=', 'lgas.id')
            ->selectRaw('lgas.lga_name AS lgaName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', $year )
            ->groupBy('lgaName')
            //->orderBy('month')
            ->orderBy('lgaName')
            ->get();

    }

    private function getLGAChairPropertyDistributionByZones($year, $lgaId){

        return DB::table('property_lists')
            ->join('zones', 'property_lists.sub_zone', '=', 'zones.sub_zone')
            ->selectRaw('zones.sub_zone AS zoneName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', $year )
            ->where('property_lists.lga_id', $lgaId)
            ->groupBy('zoneName')
            //->orderBy('month')
            ->orderBy('zoneName')
            ->get();

    }

    private function getLGAChairPropertyDistributionByLGA($year, $lgaId){

        return DB::table('property_lists')
            ->join('lgas', 'property_lists.lga_id', '=', 'lgas.id')
            ->selectRaw('lgas.lga_name AS lgaName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', $year )
            ->where('property_lists.lga_id', $lgaId)
            ->groupBy('lgaName')
            //->orderBy('month')
            ->orderBy('lgaName')
            ->get();

    }
    /*

    private function getPropertyDistributionByZones($year){

        $propertyData = DB::table('property_lists')
            ->join('zones', 'property_lists.sub_zone', '=', 'zones.sub_zone')
            ->selectRaw('MONTH(property_lists.created_at) AS month, zones.sub_zone AS zoneName, COUNT(property_lists.id) AS totalProperties')
            ->whereYear('property_lists.created_at', '=', $year ) // Filter for the current year
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
                'label' => $zoneName,
                'data' => array_fill(0, 12, 0), // Initialize all months with 0
                'backgroundColor' => '#' . substr(md5($zoneName), 0, 6), // Generate unique color
            ];

            foreach ($data as $property) {
                $dataset['data'][$property->month - 1] = $property->totalProperties;
            }

            $chartData['datasets'][] = $dataset;
        }
        return $chartData;

    }*/


   /* private function getPropertyDistributionByLGA(){

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

    }*/


    public function chartTest(Request $request){
        return response()->json([
            'billAmount'=>Billing::getCurrentYearMonthlyBillAmount($request->year),
            'amountPaid'=>Billing::getCurrentYearMonthlyAmountPaid($request->year),
            'byZones'=>Billing::getCurrentYearBillsByZone($request->year),
            'byLGA'=>Billing::getCurrentYearBillsByLGA($request->year),
            'paymentByLGA'=>Billing::getCurrentYearPaymentByLGA($request->year),
        ]);
    }

    public function actionBill(Request $request){

        $validator = Validator::make($request->all(),
            [
                "requestId"=>"required",
                "actionedBy"=>"required",
                "action"=>"required",
            ],
            [
                "requestId.required"=>"Whoops! Something is missing",
                "actionedBy.required"=>"Who action this objection?",
                "action.required"=>"Missing status update",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = Billing::find( $request->requestId);
        if (!$record) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }
        if($request->action == 1){ //review
            $record->status = $request->action;
            $record->reviewed_by = $request->actionedBy;
            $record->date_reviewed = now();
            $record->save();
        }

        if( $request->action == 2){ //verify
            $record->status = $request->action;
            $record->actioned_by = $request->actionedBy;
            $record->date_actioned = now();
            $record->save();
        }
        if($request->action == 3){ //authorization
            $record->status = $request->action;
            $record->authorized_by = $request->actionedBy;
            $record->date_authorized = now();
            $record->save();
        }
        if($request->action == 4){ //approved
            $record->status = $request->action;
            $record->approved_by = $request->actionedBy;
            $record->date_approved = now();
            $record->save();

        }
        if($request->action == 5){ //return bill
            $record->status = $request->action;
            $record->returned = 1;
            $record->returned_by = $request->actionedBy;
            $record->date_returned = now();
            $record->return_reason = $request->reason ?? '';
            $record->save();

        }
        return response()->json(['message' => 'Success! Action successful.'], 201);
    }

    public function rollbackBill($year){
        $bills = Billing::where('year', $year)->get();
        if(count($bills) > 0){
            foreach($bills as $bill){
                if($bill->status <= 2){
                    $bill->delete();
                }

            }
        }
        return response()->json(['message' => 'Success! Action successful.'], 200);

    }

    public function toggleBillType(Request $request){

        $validator = Validator::make($request->all(),
            [
                "requestId"=>"required",
                "actionedBy"=>"required",
                "action"=>"required",
            ],
            [
                "requestId.required"=>"Whoops! Something is missing",
                "actionedBy.required"=>"Who action this objection?",
                "action.required"=>"Missing status update",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $record = Billing::find( $request->requestId);
        if (!$record) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }

        $record->status = 0; //$request->action;
        $record->special = $request->action;
        //$record->date_actioned = now();
        $record->save();


        return response()->json(['message' => 'Success! Action successful.'], 201);
    }


    public function handleBillBulkAction(Request $request){

        $validator = Validator::make($request->all(),
            [
                "ids"=>"required|array",
                "ids.*"=>"required",
                "action"=>"required",
                'actionedBy'=>"required"
            ],
            [
                "ids.required"=>"Missing info",
                "ids.array"=>"Mismatch data",
                "action.required"=>"Missing status update",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $records = Billing::whereIn('id', $request->ids)->get();
        if (count($records) <= 0) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }
        switch ($request->action){
            case 'review':
                foreach($records as $record){
                    $record->status = 1;
                    $record->reviewed_by = $request->actionedBy;
                    $record->date_reviewed = now();
                    $record->save();
                }
                break;
            case 'verify':
                foreach($records as $record){
                    $record->status = 2;
                    $record->actioned_by = $request->actionedBy;
                    $record->date_actioned = now();
                    $record->save();
                }
            break;
            case 'authorize':
                foreach($records as $record){
                    $record->status = 3;
                    $record->authorized_by = $request->actionedBy;
                    $record->date_authorized = now();
                    $record->save();
                }
                break;
            case 'approve':
                foreach($records as $record){
                    $record->status = 4;
                    $record->approved_by = $request->actionedBy;
                    $record->date_approved = now();
                    $record->save();
                }
                break;
        }


        return response()->json(['message' => 'Success! Action successful.'], 201);
    }




    public function showLGAChairDashboardStatistics(Request $request){
        return new LGAChairDashboardStatisticsResource($request);
    }


    public function showLGAChairPropertyDistributionByZones(Request $request){

        $user = User::find($request->user);
        if(empty($user)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        return response()->json(
            [
                'zone'=>$this->getLGAChairPropertyDistributionByZones($request->year, $user->lga),
                'lga'=>$this->getLGAChairPropertyDistributionByLGA($request->year, $user->lga)
            ]
        );
    }

    public function showLGAChairBillDataOnDashboard(Request $request){
        $user = User::find($request->user);
        if(empty($user)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        return response()->json($this->getLGAChairMonthlyBillPaymentByYear($request->year, $user->lga));
    }


    public function LGAChairChartTest(Request $request){
        $user = User::find($request->user);
        if(empty($user)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        return response()->json([
            'billAmount'=>Billing::getLGAChairCurrentYearMonthlyBillAmount($request->year, $user->lga),
            'amountPaid'=>Billing::getLGAChairCurrentYearMonthlyAmountPaid($request->year, $user->lga),
            'byZones'=>Billing::getLGAChairCurrentYearBillsByZone($request->year, $user->lga),
            'byLGA'=>Billing::getLGAChairCurrentYearBillsByLGA($request->year, $user->lga),
            'paymentByLGA'=>Billing::getLGAChairCurrentYearPaymentByLGA($request->year, $user->lga),
        ]);
    }


    public function billSearch(Request $request){
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        $status = $request->status ?? 0;
        $special = $request->special ?? 0;
        if(!$keyword){
            return response()->json([
                "errors"=>"No search term submitted"
            ],404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json(['data'=>BillSearchResource::collection(Billing::searchBills($keyword, $propertyUse, $status, $special)) ]);
    }

    public function searchOutstandingBills(Request $request){
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        $status = $request->status ?? 0;
        $objection = $request->objection ?? 0;
        $paid = $request->paid ?? 0;
        $special = $request->special ?? 0;
        if(!$keyword){
            return response()->json([
                "errors"=>"No search term submitted"
            ],404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json(['data'=>BillSearchResource::collection(Billing::searchOutstandingBills($keyword, $propertyUse, $status, $special, $objection, $paid)) ]);
    }

}

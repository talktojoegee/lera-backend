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
use App\Http\Resources\PrintByBatchResource;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\RetrieveBillResource;
use App\Jobs\NotifyKogiRemsJob;
use App\Jobs\ProcessBillingJob;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\EditBillLog;
use App\Models\KogiRemsNotification;
use App\Models\Lga;
use App\Models\ManualReceipt;
use App\Models\MinimumLuc;
use App\Models\Objection;
use App\Models\Owner;
use App\Models\PrintBillLog;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        ProcessBillingJob::dispatch($lgaId, $year, $billedBy)->onQueue('data_sync_queue');
        return response()->json(['data'=>"Bill queued for processing!"], 201);

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
    public function showObjectedBills(Request $request){
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
        $objectionIds = Objection::all()->pluck('bill_id')->toArray();
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getObjectedBillsByStatus($limit, $skip, $status, $propertyUse, $objectionIds)),
            'total'=>Billing::getObjectedBillsByParamsByStatus($status, $propertyUse)->count(),
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
        $gross = Billing::getGrossAllBillsByParams(0, $propertyUse)->sum('bill_amount');
        $paid = Billing::getAllBillsPaymentByParams( 0 , 4, $propertyUse)->sum('paid_amount');
        $partly = Billing::where('paid_amount', '>', 0)->where('paid', 0)->sum('paid_amount');
        return response()->json([
            'data'=>PaidBillResource::collection(Billing::getAllPaidBills($limit, $skip, 1, 0, 4, $propertyUse)),
            'total'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->count(),
            'grossBills'=>$gross,
            //'grossBills'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('bill_amount'),
            //'grossBills'=>Billing::getAllBillsByParams(1,0,4, $propertyUse)->sum('bill_amount'),
            'partial'=>$partly,
            'grossAmountPaid'=>$paid,
            'balanceAmount'=>$gross - ($paid + $partly),
        ]);
    }

    public function showPartlyPaidBills(Request $request){
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
        $gross = Billing::getGrossAllBillsByParams(0, $propertyUse)->sum('bill_amount');
        $paid = Billing::getAllBillsPaymentByParams( 0 , 4, $propertyUse)->sum('paid_amount');
        $partlyPaid = Billing::getAllPartlyPaidBills($limit , $skip , 0 , $propertyUse);
        //return response()->json(['data'=>$skip],200);
        return response()->json([
            'data'=>PaidBillResource::collection($partlyPaid),
            'total'=>$partlyPaid->count(),
            'grossBills'=>$gross,
            'partial'=>$partlyPaid->sum('paid_amount'),
            'grossAmountPaid'=>$paid,
            'balanceAmount'=>$gross - $paid,
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
                        $log->prev_luc = ceil($record->bill_amount) ?? 0;
                        $log->prev_assess_value = $record->assessed_value;

                        $log->cur_la = $request->la;
                        $log->cur_ba = $request->ba;
                        $log->cur_rr = $request->rr;
                        $log->cur_dr = $request->dr;
                        $log->cur_br = $request->br;
                        $log->cur_lr = $request->lr ?? 0;
                        $log->cur_luc = ceil($request->lucAmount);
                        $log->cur_assess_value = $request->assessedValue;
                        $log->save();

        $code = $record->pav_code;
        $record->assessed_value = $request->assessedValue;
        $record->bill_amount = ceil($request->lucAmount);
        //$record->bill_rate = $request->chargeRate;
        $record->returned = 2; //processed
        $record->status = 1; //take it back to pending for it to re-enter the workflow process
        $record->la = $request->la ?? 0;
        $record->ba = $request->ba ?? 0;
        $record->rr = $request->rr ?? 0;
        $record->dr = $request->dr ?? 0;
        $record->br = $request->br ?? 0;
        $record->lr = $request->lr ?? 0;
        $record->pav_code = str_replace("B", "CS", $code);
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
            //notify virtualization table on GIS
            $property = PropertyList::where('building_code', $record->building_code)->first();
            if($property){
                DB::connection('pgsql')
                    ->table('virtualization')
                    ->insert([
                        'building_code' => $property->building_code,
                        'assessment_code' => $record->assessment_no,
                        'bill_approved' =>true,
                        'bill_distributed' => false ,
                        'bill_paid' => 0,
                        'bill_approved_date' => now(),
                        'bill_distributed_date' => null ,
                        'bill_paid_date' => null ,
                        'property_name' => $property->property_name ?? '',
                        'property_address' => $property->address,
                        'property_lga' => $property->lga_id,
                        'property_zone' => $property->sub_zone ?? '',
                        'property_ward' => $property->ward ?? '',
                        'property_image' => $property->image ?? '',
                        'bill_delivery_image' => null ,
                        'property_longitude' => $property->longitude ?? '',
                        'property_latitude' => $property->latitude ?? '',
                        'created_at' => now(),
                        'property_category' => $property->class_id ?? '',
                    ]);
            }


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
            case 'return':
                foreach($records as $record){
                    $record->status = 5;
                    $record->returned = 1;
                    $record->returned_by = $request->actionedBy;
                    $record->date_returned = now();
                    $record->return_reason = 'Bulk return action';
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

    public function searchAllPendingBills(Request $request){
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
        return response()->json(['data'=>BillSearchResource::collection(Billing::searchAllPendingBills($keyword, $propertyUse, $special)) ]);
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


    public function showBillsForPrinting(Request $request){
        $lgaId = $request->lga;
        $type = $request->type;
        $billIds = PrintBillLog::pluck('bill_id')->toArray();
        switch ($type){
            case 'lga':
                $bills =  Billing::when($lgaId > 0, function($query) use ($lgaId) {
                    return $query->where('lga_id', $lgaId);
                })
                    ->whereNotIn('id', $billIds)
                    ->where('status', 4)
                    ->where('objection', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
            case 'zone':
                $bills =  Billing::where('zone_name',$lgaId) //zone_name : A1 || C2...
                    ->whereNotIn('id', $billIds)
                    ->where('status', 4)
                    ->where('objection', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
            case 'ward':
                $bills =  Billing::where('ward',$lgaId) //zone_name : Lokoja E ...
                ->whereNotIn('id', $billIds)
                ->where('status', 4)
                    ->where('objection', 0)
                    ->orderBy('id', 'ASC')
                    ->get();
                return response()->json(['data'=>OutstandingBillResource::collection($bills)],200);
        }

    }


    public function deleteBill(Request $request){
        $validator = Validator::make($request->all(),
            [
                "billId"=>"required",
                'actionedBy'=>"required"
            ],
            [
                "billId.required"=>"Missing info",
                "actionedBy.required"=>"Who is calling the shots?",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::find($request->billId);
        if(empty($bill)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        if($bill->paid_amount > 0){
            return response()->json([
                "errors"=>"This bill has payment. It can't be deleted."
            ],422);
        }
        $paymentLog = BillPaymentLog::where('bill_master', $request->billId)->get();
        if(empty($paymentLog)){
            return response()->json([
                "errors"=>"This bill has payment. It can't be deleted."
            ],422);
        }
        $bill->delete();
        //log activity
        $user = User::find($request->actionedBy);
        if(!empty($user)){
            $lga = Lga::find($bill->lga_id);
            $class = PropertyClassification::find($bill->class_id);
            $lgaName = !empty($lga) ? $lga->lga_name : '-';
            $className = !empty($class) ? $class->class_name : '-';
            $title = "Bill {$bill->assessment_no} deleted.";
            $narration = "{$user->name} deleted bill with the assessment no: {$bill->assessment_no} and building code: {$bill->building_code}. Details are as shown below:
            LUC: {$bill->bill_amount}, Assessment Value: {$bill->assessed_value}, Charge Rate: {$bill->cr}, BA: {$bill->ba}, RR: {$bill->rr},
            DR: {$bill->dr}, LR: {$bill->lr}, LA: {$bill->la}, BR: {$bill->br}, Billing Code: {$bill->pav_code}, Year: {$bill->year},
            LGA: {$lgaName}, Zone: {$bill->zone_name}, Ward: {$bill->ward}, Class: {$className}, Property Use: {$bill->property_use}";
            ActivityLog::LogActivity($title, $narration, $user->id);
        }
        return response()->json(['data'=>"Action successful"],200);
    }

    public function initiatePrintingRequest(Request $request){
        $validator = Validator::make($request->all(),
            [
                "bills"=>"required|array",
                "bills.*"=>"required",
                'actionedBy'=>"required",
                'printedBy'=>"required",
            ],
            [
                "bills.required"=>"Select bills to print",
                "printedBy.required"=>"Something is missing",
                "actionedBy.required"=>"Who is calling the shots?",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $batchCode = substr(sha1(time()),30,40);
        foreach($request->bills as $bill){
            $billDetail = Billing::find($bill->billId);
            if(!empty($billDetail)){
                $exist = PrintBillLog::where('bill_id', $bill->billId)->first();
                if(empty($exist)){
                    PrintBillLog::create([
                        'bill_id'=>$bill->billId,
                        'user_id'=>$request->actionedBy,
                        'batch_code'=>$batchCode,
                        'assessment_no'=>$billDetail->assessment_no ??'',
                        'printed_by'=>$request->printedBy,
                    ]);
                }

            }
        }
        return response()->json([
            "data"=>"Billing printing done!"
        ],200);
    }

    public function getPrintByBatch(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $log = PrintBillLog::fetchPrintBillLogByBatchCode($limit, $skip);
        if(empty($log)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        return response([
            'data'=>PrintByBatchResource::collection($log),
            'total'=>$log->count(),
        ],200);

    }
    public function viewBatchPrinting(Request $request){
        $batch = $request->batch;
        if(empty($batch)){
            return response()->json([
                "errors"=>"Something went wrong. Try again later."
            ],422);
        }
        $log = PrintBillLog::viewPrintBillLogByBatchCode($batch);

        if(empty($log)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        $billIds = $log->pluck('bill_id')->toArray();
        if(count($billIds) <= 0){
            return response()->json([
                "errors"=>"There's nothing to print."
            ],404);
        }
        $bills = Billing::whereIn('id', $billIds)->get();
        return response(['data'=>BillDetailResource::collection($bills)],200);

    }

    public function searchBillByAssessment(Request $request){
        $keyword = $request->keyword;
        if(empty($keyword)){
            return response()->json([
                "detail"=>"Whoops!",
                "message"=>"Something went wrong. Try again later."
            ],422);
        }
        $log = PrintBillLog::searchForBillLog($keyword);

        if(empty($log)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        return response(['data'=>PrintByBatchResource::collection($log)],200);

    }

    public function getWards(){
        $wards = Billing::select('ward')->distinct()->get();
        return response()->json(['data'=>$wards], 200);
    }
    public function getZones(){
        $zones = Billing::select('zone_name')->distinct()->get();
        return response()->json(['data'=>$zones], 200);
    }


    public function updateDelivery(Request $request){
        $validator = Validator::make($request->all(),[
            "deliveryDate"=>"required|date",
            "delivered"=>"required",
            "acknowledgement"=>"required",
            "buildingCode"=>"required",
            "year"=>"required",
        ],[
            "deliveryDate.required"=>"Enter delivery date",
            "deliveryDate.date"=>"Enter a valid date",
            "delivered.required"=>"Indicate whether this bill was delivered",
            "acknowledgement.required"=>"Indicate acknowledgement",
            "buildingCode.required"=>"Provide building code",
            "year.required"=>"Provide year",
        ]);
        if($validator->fails()){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::where('building_code', $request->buildingCode)->where('year', $request->year)->first();
        if(empty($bill)){
            return response()->json([
                "message"=>"Record not found"
            ],404);
        }

        $bill->delivered = $request->delivered;
        $bill->delivery_date = $request->deliveryDate;
        $bill->acknowledgement = $request->acknowledgement;
        $bill->save();

        return response()->json(['data'=>'Action successful'],200);

    }

    public function checkBillExists(Request $request)
    {
        $validator = Validator::make($request->all(),[
          'assessmentNumber'=>'required'
        ],[
            'assessmentNumber.required'=>'Enter assessment number '
        ]);
        if($validator->fails()){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $bill = Billing::where('assessment_no', $request->assessmentNumber)
            ->where('objection', 0)
            ->where('status', 4)
            ->where('paid', 0)
            ->first();
        return new BillDetailResource($bill);
    }


    public function storeReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assessmentNumber' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'proofOfPayment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'actionedBy' => 'required',
            'receiptNo' => 'required',
            'entryDate' => 'required|date',
            'referenceNo' => 'required',
            'kgTin' => 'required',
            'email' => 'required',
            'customerName' => 'required',
            'branchName' => 'required',
            'bankName' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        //$path = $request->file('proofOfPayment')->store('proofs', 'public');
        /*$uploadDir = public_path('assets/drive/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file = $request->file('proofOfPayment');
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $file->move($uploadDir, $filename);*/
        $filename = null; // uniqid() . '_' . $file->getClientOriginalName();
        if ($request->hasFile('proofOfPayment') && $request->file('proofOfPayment')->isValid()) {
            $file = $request->file('proofOfPayment');
            $filename = time() . '_' . $file->getClientOriginalName();

            $file->storeAs('', $filename, 'assets_drive');
        }
         ManualReceipt::create([
            'issued_by'=>$request->actionedBy,
            'assessment_no'=>$request->assessmentNumber,
            'amount'=>$request->amount,
            'proof_of_payment'=>$filename,
            'receipt_no'=>$request->receiptNo,
            'entry_date'=>$request->entryDate,
            'bank_name'=>$request->bankName ?? '',
            'branch_name'=>$request->branchName ?? '',
            'customer_name'=>$request->customerName ?? '',
            'email'=>$request->email ?? '',
            'kgtin'=>$request->kgTin ?? '',
            'reference'=>$request->referenceNo ?? '',
             'url'=>Str::uuid()
        ]);
        $userId = $request->actionedBy ?? 0;
        $user = User::find($userId);
        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $title = 'New Receipt Issued';
        $narration = "{$user->name}({$user->id_no}) issued a receipt with the assessment number:  {$request->assessmentNumber}. Amount: {$request->amount}; Reference: {$request->referenceNo}";
        ActivityLog::LogActivity($title, $narration , $user->id);
        return response()->json([
            'message' => 'Action successful',
        ], 200);
    }


    public function getManualReceipts(Request $request){
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
        $assessmentNos = ManualReceipt::pluck('assessment_no')->toArray();
        $bills = Billing::join('manual_receipts', 'billings.assessment_no', '=', 'manual_receipts.assessment_no')
            ->join('users', 'manual_receipts.issued_by', '=', 'users.id')
            ->join('property_lists', 'property_lists.id', '=', 'billings.property_id')
            ->whereIn('billings.assessment_no', $assessmentNos)
            ->whereIn('billings.property_use', $propertyUse)
            ->where('billings.special', 0)
            ->orderBy('manual_receipts.id', 'DESC')
            ->skip($skip)
            ->take($limit)
            ->get([
                'billings.*',
                'users.name',
                'users.id_no',
                'property_lists.property_name',
                'manual_receipts.receipt_no',
                'manual_receipts.entry_date',
                'manual_receipts.amount as receiptAmount',
                'manual_receipts.proof_of_payment',
                'manual_receipts.status',
                'manual_receipts.customer_name',
                'manual_receipts.bank_name',
                'manual_receipts.branch_name',
                'manual_receipts.email',
                'manual_receipts.kgtin',
                'manual_receipts.reference',
                'manual_receipts.email',
                'manual_receipts.url'
            ]);


        return response()->json([
            'data'=>ReceiptResource::collection($bills),
            'total'=>$bills->count(),
        ],200);
    }

    public function showReceiptDetail(Request $request){
        $userId = $request->user ?? 0;
        $user = User::find($userId);
        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        $bill = Billing::join('manual_receipts', 'billings.assessment_no', '=', 'manual_receipts.assessment_no')
            ->join('users', 'manual_receipts.issued_by', '=', 'users.id')
            ->join('property_lists', 'property_lists.id', '=', 'billings.property_id')
            ->where('manual_receipts.url', $request->url)
            ->whereIn('billings.property_use', $propertyUse)
            ->where('billings.special', 0)
            ->first([
                'billings.*',
                'users.name',
                'users.id_no',
                'property_lists.property_name',
                'manual_receipts.receipt_no',
                'manual_receipts.entry_date',
                'manual_receipts.amount as receiptAmount',
                'manual_receipts.proof_of_payment',
                'manual_receipts.status',
                'manual_receipts.customer_name',
                'manual_receipts.bank_name',
                'manual_receipts.branch_name',
                'manual_receipts.email',
                'manual_receipts.kgtin',
                'manual_receipts.reference',
                'manual_receipts.email',
                'manual_receipts.actioned_by',
                'manual_receipts.date_actioned',
                'manual_receipts.url'
            ]);
        if (!$bill) {
            return response()->json(['message' => 'Receipt not found.'], 404);
        }
        $actualBill = Billing::where('assessment_no', $bill->assessment_no)->first();

        if(isset($bill->actioned_by)){
            $actionedBy = User::find($bill->actioned_by);
            $actionUser = $actionedBy->name."($actionedBy->id_no)";
        }

        return response()->json([
            'receipt'=>new ReceiptResource($bill),
            'actionedBy'=> $actionUser ?? '',
            'dateActioned'=>date('d/m/Y', strtotime($bill->date_actioned)) ?? '',
            'bill'=>new  OutstandingBillResource($actualBill),
        ],200);
    }

    public function downloadProofOfPayment(Request $request)
    {
        try {
            $filename = trim($request->slug);
            $attachment = ManualReceipt::where('proof_of_payment', $filename)->first();

            if (!$attachment) {
                return response()->json([
                    'message' => 'Whoops! No record found'
                ], 404);
            }

            $file_path = public_path('assets/drive/' . $filename);

            if (file_exists($file_path)) {
                return response()->file($file_path, [
                    'Content-Type' => mime_content_type($file_path),
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]);
            } else {
                return response()->json([
                    'message' => 'File not found'
                ], 404);
            }

        } catch (\Exception $ex) {
            return response()->json([
                'message' => 'Whoops! Something went wrong',
                'error' => $ex->getMessage()
            ], 500);
        }
    }


    public function actionReceipt(Request $request){
        $validator = Validator::make($request->all(), [
            'receiptNo' => 'required|string',
            'actionedBy' => 'required',
            'action' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $action = $request->action;
        if(!isset($action)){
            return response()->json([
                'message' => 'Whoops! Something went wrong'
            ], 404);
        }
        $userId = $request->actionedBy ?? 0;
        $user = User::find($userId);
        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $receiptNo = $request->receiptNo;
        $record = ManualReceipt::where('url', $receiptNo)->where('status', 0)->first();
        if(empty($record)){
            return response()->json([
                'message' => 'Whoops! No record found'
            ], 404);
        }

        $bill = Billing::where('assessment_no', $record->assessment_no)
            ->where('paid', 0)
            ->first();
        if(empty($bill)){
            return response()->json([
                'message' => 'Whoops! No record found'
            ], 404);
        }

        switch ($action){
            case 'approve':
                $this->__updateReceipt($record, 1, $request->actionedBy);
                $this->__processPayment($bill, $record, $request->actionedBy);
            break;
            case 'discard':
                $this->__updateReceipt($record, 2, $request->actionedBy);
            break;

            default:
                return response()->json([
                    'message' => 'Whoops! No record found'
                ], 404);
        }
        $title = 'Receipt Posting';
        $narration = "{$user->name}({$user->id_no}) posted receipt({$record->receipt_no}):  {$record->assessmentNumber}. Amount: {$record->amount}; Reference: {$record->referenceNo}";
        ActivityLog::LogActivity($title, $narration , $user->id);
        return response()->json([
            'data'=>'Action successful'
        ], 200);
    }

    private function __updateReceipt($record, $status, $actionedBy){
        $record->status = $status;
        $record->actioned_by = $actionedBy;
        $record->date_actioned = now();
        $record->save();
    }

    private function __processPayment($bill, $record, $actionedBy){
        $billList = Billing::where('building_code', $bill->building_code)
            ->where('year','<=', $bill->year)
            ->where('paid', 0)
            ->orderBy('id', 'ASC')
            ->get();
        if(!empty($billList)){
            $transAmount = $record->amount;
            foreach ($billList as $item){
                $balance = $item->bill_amount - $item->paid_amount;
                $transBalance = $transAmount - $balance;
                if($balance > 0){
                    if($transAmount > $balance){
                        $item->paid_amount += $balance;
                        $item->payment_ref = $record->reference;
                        $item->paid = 1;
                        $item->date_paid = now();
                        $item->paid_by = 1;
                        $item->save();
                        $this->_registerInPaymentLog($item->id, $actionedBy, $balance, $record->receipt_no, $record->reference,
                            $record->assessment_no, $record->bank_name, $record->branch_name, 'MANUAL', $record->customer_name,
                            $record->email, $record->kgtin, $record->reference, $record->entry_date, '234');
                    }
                    else{
                        if($transAmount > 0){
                            $item->paid_amount += $transAmount;
                            $item->payment_ref = $record->reference;
                            $item->save();
                            if($item->paid_amount == $item->bill_amount){
                                $item->paid = 1;
                                $item->date_paid = now();
                                $item->paid_by = 1;
                                $item->save();
                            }
                            $this->_registerInPaymentLog($item->id, $actionedBy, $transAmount, $record->receipt_no, $record->reference,
                                $record->assessment_no, $record->bank_name, $record->branch_name, 'MANUAL', $record->customer_name,
                                $record->email, $record->kgtin, $record->reference, $record->entry_date, '234');
                        }
                    }
                }
                $transAmount = $transBalance;
            }
        }
    }

    private function _registerInPaymentLog($billMasterId, $paidBy, $amount, $receiptNo, $paymentCode,
                                           $assessmentNo, $bankName, $branchName, $payMode, $customerName,
                                           $email, $kgTin, $transRef, $transDate, $phoneNumber){
        $bill = Billing::find($billMasterId);
        if($bill){
            BillPaymentLog::create([
                "bill_master"=>$billMasterId,
                "paid_by"=>$paidBy,
                "amount"=>$amount,
                "receipt_no"=>$receiptNo,
                "payment_code"=>$paymentCode,
                "assessment_no"=>$assessmentNo,

                "building_code"=>!empty($bill) ? $bill->building_code : '',
                "lga_id"=>!empty($bill) ? $bill->lga_id : '',
                "ward"=> $bill->ward ?? '',
                "zone"=> $bill->zone_name ?? '',

                "bank_name"=>$bankName,
                "branch_name"=>$branchName,
                "pay_mode"=>$payMode,
                "customer_name"=>$customerName,
                "email"=>$email,
                "kgtin"=>$kgTin,
                "entry_date"=>Carbon::parse($transDate)->format('Y-m-d'),
                "token"=>'MANUAL_RECEIPT',
                "trans_ref"=>$transRef,
                "reference"=>$transRef,

            ]);



            //update property
            $property = PropertyList::where('building_code', $bill->building_code)->first();
            if(!empty($property)){
                $property->owner_email = $email;
                $property->owner_name = $customerName;
                $property->owner_gsm = $phoneNumber;
                $property->owner_kgtin = $kgTin ?? null;
                $property->save();
            }
            //update owner
            if(!empty($property) && !empty($bill)){
                $owner = Owner::where('kgtin', $kgTin)->first();
                if(empty($owner)){
                    Owner::create([
                        "email"=>$email,
                        "kgtin"=>$kgTin,
                        "name"=>$customerName,
                        "telephone"=>$phoneNumber,
                        "lga_id"=>$bill->lga_id,
                        "added_by"=>$paidBy,
                        "res_address"=>$property->address ?? 'N/A'
                    ]);
                }else{
                    $owner->email = $email;
                    $owner->kgtin = $kgTin;
                    $owner->name = $customerName;
                    $owner->telephone = $phoneNumber;
                    $owner->lga_id = $bill->lga_id;
                    $owner->res_address = $property->address ?? 'N/A';
                    $owner->save();
                }
            }
            //Kogi rems notification register
            KogiRemsNotification::create([
                "assessmentno"=>$bill->assessment_no,
                "buildingcode"=>$bill->building_code,
                "kgtin"=>$kgTin ?? null,
                "name"=>$customerName,
                "amount"=>$amount,
                "phone"=>$phoneNumber,
                "email"=>$email,
                "transdate"=>Carbon::parse($transDate)->format('Y-m-d') ?? now(),
                "transRef"=>$transRef,
                "paymode"=>"eTranzact",
                "bank_name"=>$bankName ?? '',
                "luc_amount"=>$bill->bill_amount,
            ]);

            NotifyKogiRemsJob::dispatch();

        }

    }

    public function fetchWards(Request $request){
        $validator = Validator::make($request->all(), [
            'lgaId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        return response()->json(['data'=>Billing::getDistinctWardByLgaId($request->lgaId)]);
    }
    public function fetchZones(Request $request){
        $validator = Validator::make($request->all(), [
            'ward' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        return response()->json(['data'=>Billing::getDistinctZonesByWard($request->ward)]);
    }


    public function filterReviewBills(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $status = $request->status ?? 0;
        $userId = $request->user ?? 0;
        $ward = $request->ward ?? 0;
        $zone = $request->zone ?? 0;
        $lga = $request->lga ?? 0;
        $user = User::find($userId);

        if(empty($user)){
            return response()->json([
                'message' => 'Whoops! Something went wrong.'
            ], 404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>OutstandingBillResource::collection(Billing::getFilteredBillsByStatus($limit, $skip,
                $status, $propertyUse, $lga,$zone,$ward)),
            'total'=>Billing::getFilteredBillsByParamsByStatus($status, $propertyUse, $lga,$zone,$ward)->count(),
        ],200);
    }

}


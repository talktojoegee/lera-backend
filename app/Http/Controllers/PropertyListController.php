<?php

namespace App\Http\Controllers;

use App\Http\Resources\BillSearchResource;
use App\Http\Resources\PropertyDetailResource;
use App\Http\Resources\PropertyListResource;
use App\Http\Resources\PropertySearchResource;
use App\Jobs\BulkForceSynchronization;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\BillPaymentLog;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyException;
use App\Models\PropertyList;
use App\Models\User;
use App\Models\Zone;
use App\Traits\UtilityTrait;
use App\Traits\PropertySyncTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropertyListController extends Controller
{
    use UtilityTrait, PropertySyncTrait;
    //

    public function __construct(){

    }


    public function showPropertyLists(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        //$perPage = $request->query('perPage', 10); // Default to 10 items per page
        $data = PropertyList::query()
        ->orderBy('id', 'DESC')
        ->paginate(10);
        //return response()->json($data);
        return PropertyListResource::collection($data);
    }


    public function getPropertyList(Request $request)
    {
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        // Base query
        $records = PropertyList::skip($skip)
            ->take($limit)
            ->orderBy('property_lists.created_at', 'desc')
            ->get();

        return  response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => PropertyList::count(),
        ]);
    }

    public function getPropertyExceptionList(Request $request)
    {
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        // Base query
        $records = PropertyException::skip($skip)
            ->take($limit)
            ->orderBy('property_exceptions.created_at', 'desc')
            ->get();

        return  response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => PropertyException::count(),
        ]);
    }


/*
    public function getPropertyList(Request $request)
    {
         $limit = (int) $request->query('limit', 10);
         $page = (int) $request->query('page', 1);
         $offset = ($page - 1) * $limit;
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        // Base query
        $query = PropertyList::join('lgas as l', 'property_lists.lga_id', '=', 'l.id')
            ->select('property_lists.*', 'l.lga_name')
            ->skip($skip)
            ->take($limit);

        // Apply search and filters
        $filters = $request->query('filter', []);
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $parts = explode('|', $filter);
                if (count($parts) === 2) {
                    [$column, $value] = $parts;
                    $query->where($column, 'like', '%' . $value . '%');
                }
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($query) use ($searchTerm) {
                $query->where('property_lists.id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('property_lists.building_code', 'like', '%' . $searchTerm . '%')
                    ->orWhere('property_lists.pav_code', 'like', '%' . $searchTerm . '%');
                //->orWhere('property_lists.pav_code', 'like', '%' . $searchTerm . '%')
                //->orWhere(DB::raw("CONCAT(e.FirstName, ' ', e.LastName)"), 'like', '%' . $searchTerm . '%')
                //->orWhere('s.ShipperName', 'like', '%' . $searchTerm . '%');
            });
        }

        $orderBy = $request->query('orderBy', []);
        $orderBy = is_array($orderBy) ? $orderBy : [$orderBy];
        if (!empty($orderBy)) {
            foreach ($orderBy as $order) {
                $parts = explode('|', $order);
                if (count($parts) === 2) {
                    [$column, $direction] = $parts;
                    $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy($column, $direction);
                }
            }
        } else {
            $query->orderBy('property_lists.created_at', 'desc');
        }

        // Clone the query to get the total count
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        // Log the SQL query
        Log::info($query->toSQL());

        // Execute the query with pagination
        $orders = $query->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'list' => $orders,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }*/


    public function showPropertyDetail(Request $request){
        $propertyDetail = PropertyList::find($request->id);

        if (!$propertyDetail) {
            return response()->json([
                'message' => 'Whoops! No record found.'
            ], 404);
        }

        return new PropertyDetailResource($propertyDetail);
    }

    public function savePropertyChanges(Request $request){
        $validator = Validator::make($request->all(), [
            "name" => "required",
            "class" => "required",
            "occupancy" => "required",
            "age" => "required",
            "propertyUse" => "required",
            "landArea" => "required",
            "address" => "required",
            "propertyId" => "required",
            "auth" => "required",
        ], [
            "name.required" => "Enter property name",
            "class.required" => "Property classification is required",
            "occupancy.required" => "Occupancy is required",
            "age.required" => "What is the building age",
            "propertyUse.required" => "Property use is required",
            "landArea.required" => "Land area is required",
            "address.required" => "Enter address",
            "auth.required" => "",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        try{
            $propertyDetail = PropertyList::find($request->propertyId);

            if (!$propertyDetail) {
                return response()->json([
                    'message' => 'Whoops! No record found.'
                ], 404);
            }
            $pavCode = $this->_getPavCode($request->class, $propertyDetail->sub_zone, $request->propertyUse);
            if(empty($pavCode)){
                return response()->json([
                    'message' => 'Whoops! No record found.'
                ], 404);
            }
            $chargeRate = ChargeRate::find($request->occupancy);

            $propertyUseMain = PropertyAssessmentValue::where('property_use', $request->propertyUse)->first();

            $propertyDetail->property_name = $request->name ?? '';
            $propertyDetail->class_id = $request->class ?? '';
            $propertyDetail->building_age = $request->age ?? '';
            $propertyDetail->occupant = $request->occupancy ?? '';
            $propertyDetail->property_use = !empty($propertyUseMain) ? $propertyUseMain->sync_word : '';
            $propertyDetail->sync_word = $request->propertyUse ?? '';
            $propertyDetail->area = $request->landArea ?? '';
            $propertyDetail->image = $request->imageUrl ?? '';
            $propertyDetail->address = $request->address ?? '';
            $propertyDetail->pav_code = $pavCode->pav_code ?? '';
            $propertyDetail->cr = $request->occupancy ?? '';
            $propertyDetail->dep_id = $request->age ?? '';
            $propertyDetail->occupier = !empty($chargeRate) ? $chargeRate->occupancy : '';
            $propertyDetail->save();
            $user = User::find($request->auth);
            if(!empty($user)){
                $title = "Property Changes";
                $narration = "{$user->name} made changes to a property with the building code: {$propertyDetail->building_code}";
                ActivityLog::LogActivity($title, $narration , $user->id);
            }
            return response()->json(['data'=>"Changes saved!"],200);
        }catch (\Exception $exception){
            return response()->json(['error'=>"Something went wrong"],422);
        }

    }

    public function forceSynchronizeProperty(Request $request){
        $validator = Validator::make($request->all(), [
            "buildingCode" => "required",
            "auth" => "required",
        ], [
            "buildingCode.required" => "Building Code is required",
            "auth.required" => "Authentication required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $result = $this->updatePropertyFromRemote($request->buildingCode);
        if (!$result['success']) {
            return response()->json([
                "errors" => $result['message']
            ], $result['code'] ?? 422);
        }
        $user = User::find($request->auth);
        if (!empty($user)) {
            $title = "Property Force Synchronization";
            $narration = "{$user->name} forcefully synchronized a property with the building code: {$request->buildingCode}";
            ActivityLog::LogActivity($title, $narration, $user->id);
        }
        return response()->json([
            "data" => "Property details updated successfully!",
            "property" => $result['data']
        ], 200);
    }

    public function bulkForceSynchronization(Request $request){
        $validator = Validator::make($request->all(), [
            "attachment" => "required|mimes:xlsx,xls|max:2048",
            "auth" => "required",
            "header" => "required",
        ], [
            "attachment.required" => "Enter property name",
            "header.required" => "Header is required",
            "auth.required" => "",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->messages()
            ], 422);
        }
        $path = $request->file('attachment')->store('uploads', 'public');

        if($request->hasFile('attachment')){
            BulkForceSynchronization::dispatch($path, $request->auth, $request->header)->onQueue('data_sync_queue');;
            return response()->json(['message' => 'File uploaded successfully. Processing started.']);
        }else{
            return response()->json([
                "errors" => "Choose a file to upload"
            ], 422);
        }


    }

    private function _getPavCode($propertyType){
        return PropertyAssessmentValue::where("property_type",$propertyType)
            ->first();
    }

    public function _getChargeRate($occupier, $landUse){
        $normalizedClassName = trim($occupier);
        switch($landUse){
            case 1:
                if ($normalizedClassName == 'Owner_3rd_Party') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (Owner and 3rd Party)%'])->first();
                }elseif ($normalizedClassName == 'Third_party') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Residential Property (without Owner in residence)%'])->first();

                } elseif ($normalizedClassName == 'Owner_occupier') {
                    return  ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                } elseif ($normalizedClassName == 'Not_Known') {
                    return ChargeRate::whereRaw("occupancy LIKE ?", ['%Owner-occupied Residential Property%'])->first();

                }
                break;
            case 2:
            case 5:
            case 6:
                return ChargeRate::find(7);
            case 3:
                return ChargeRate::find(4);
            case 4:
            case 7:
            case 8:
            case 9:
                return ChargeRate::find(3);
            case 10:

            case 11:
                return ChargeRate::find(9);
            case 12:
                return ChargeRate::find(8);
        }

    }



    public function _getClass($className){
        $normalizedClassName = trim(strtolower($className));
        if ($normalizedClassName == 'government') {
            return PropertyClassification::where("class_name",  'Kogi State Govt.')->first();

        }elseif ($normalizedClassName == 'residential') {
            return PropertyClassification::where("class_name",  'Residential')->first();

        } elseif (in_array($normalizedClassName, ['recreational', 'religious', 'commercial'])) {
            return PropertyClassification::where("class_name",  'Commercial')->first();

        } elseif ($normalizedClassName == 'educational') {
            return PropertyClassification::where("class_name",  'Education (Private)')->first();

        } elseif ($normalizedClassName == 'health') {
            return PropertyClassification::where("class_name",  'Hospital')->first();

        } elseif ($normalizedClassName == 'open land') {
            return PropertyClassification::where("class_name",  'Vacant Properties & Open Land')->first();

        }  else {
            return null;
        }
    }



    private function _getZoneCharacter($char):string{
        switch (substr($char,0,1)){
            case 'A':
                return 'A';
            case 'B':
                return 'B';
            case 'C':
                return 'C';
            case 'D':
                return 'D';
            case 'E':
                return 'E';
            case 'F':
                return 'F';
            case 'G':
                return 'G';
            case 'H':
                return 'H';
            default:
                return 'Z';

        }
    }


    public function searchAllProperties(Request $request){
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        if(!$keyword){
            return response()->json([
                "errors"=>"No search term submitted"
            ],404);
        }
        $propertyUse = explode(',', $user->sector);
        return response()->json(['data'=>PropertySearchResource::collection(PropertyList::searchAllProperties($keyword,$propertyUse)) ]);
    }

    public function searchAllPropertyException(Request $request){
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        if(!$keyword){
            return response()->json([
                "errors"=>"No search term submitted"
            ],404);
        }
        //$propertyUse = explode(',', $user->sector);
        return response()->json([
            'data'=>PropertySearchResource::collection(PropertyException::searchAllPropertyException($keyword))
        ]);
    }


    public function deleteProperty(Request $request){
        $validator = Validator::make($request->all(),
            [
                "propertyId"=>"required",
                'actionedBy'=>"required"
            ],
            [
                "propertyId.required"=>"Missing info",
                "actionedBy.required"=>"Who is calling the shots?",
            ]
        );
        if($validator->fails() ){
            return response()->json([
                "errors"=>$validator->messages()
            ],422);
        }
        $property = PropertyList::find($request->propertyId);
        if(empty($property)){
            return response()->json([
                "errors"=>"No record found."
            ],404);
        }
        $bill = Billing::where('property_id', $request->propertyId)->first();
        if(!empty($bill)){
            return response()->json([
                "errors"=>"This property has a paid bill. It can't be deleted."
            ],422);
        }
        $property->delete();
        //log activity
        $user = User::find($request->actionedBy);
        if(!empty($user)){
            $lga = Lga::find($property->lga_id);
            $class = PropertyClassification::find($property->class_id);
            $lgaName = !empty($lga) ? $lga->lga_name : '-';
            $className = !empty($class) ? $class->class_name : '-';
            $title = "Property {$property->building_code} deleted.";
            $narration = "{$user->name} deleted property with the building code:  {$property->building_code}. Details are as shown below:
           Billing Code: {$property->pav_code},
            LGA: {$lgaName}, Zone: {$property->sub_zone}, Ward: {$property->ward}, Class: {$className}, Property Use: {$property->property_use}";
            ActivityLog::LogActivity($title, $narration, $user->id);
        }
        return response()->json(['data'=>"Action successful"],200);
    }



    public function showExemptedProperties(Request $request){
        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $chargeIds = ChargeRate::where('rate', 0)->pluck('id')->toArray();
        $records = PropertyList::whereIn('cr', $chargeIds)->skip($skip)
            ->take($limit)
            ->orderBy('id', 'desc')
            ->get();
        return  response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => PropertyList::whereIn('cr', $chargeIds)->orderBy('id', 'DESC')->count(),
        ]);
    }


}

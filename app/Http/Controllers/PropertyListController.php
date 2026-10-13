<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyDetailResource;
use App\Http\Resources\PropertyListResource;
use App\Http\Resources\PropertySearchResource;
use App\Jobs\BulkForceSynchronization;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\Lga;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyClassification;
use App\Models\PropertyException;
use App\Models\PropertyList;
use App\Models\User;
use App\Traits\PropertySyncTrait;
use App\Traits\ResolvesActingUser;
use App\Traits\UtilityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropertyListController extends Controller
{
    use PropertySyncTrait, ResolvesActingUser, UtilityTrait;
    //

    public function __construct() {}

    /**
     * @return list<int>
     */
    private function allowedLgaIds(Request $request): array
    {
        $user = $this->resolveActingUser($request);

        return $user ? $user->sectorLgaIds() : [];
    }

    private function unauthorizedLgaResponse()
    {
        return response()->json([
            'message' => 'Unauthorized LGA',
        ], 403);
    }

    private function propertyOutsideAllowedLgas(PropertyList $property, array $allowedLgaIds): bool
    {
        return $allowedLgaIds !== []
            && ! in_array((int) $property->lga_id, $allowedLgaIds, true);
    }

    public function showPropertyLists(Request $request)
    {
        $user = $this->resolveActingUser($request);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $allowedLgaIds = $user->sectorLgaIds();
        $data = PropertyList::query()
            ->forAllowedLgas($allowedLgaIds)
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return PropertyListResource::collection($data);
    }

    public function getPropertyList(Request $request)
    {
        $user = $this->resolveActingUser($request);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $allowedLgaIds = $user->sectorLgaIds();

        $query = PropertyList::query()->forAllowedLgas($allowedLgaIds);

        $records = (clone $query)
            ->skip($skip)
            ->take($limit)
            ->orderBy('property_lists.created_at', 'desc')
            ->get();

        return response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => $query->count(),
        ]);
    }

    public function getPropertyExceptionList(Request $request)
    {
        $user = $this->resolveActingUser($request);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $allowedLgaIds = $user->sectorLgaIds();

        $query = PropertyException::query()
            ->when($allowedLgaIds !== [], fn ($q) => $q->whereIn('lga_id', $allowedLgaIds));

        $records = (clone $query)
            ->skip($skip)
            ->take($limit)
            ->orderBy('property_exceptions.created_at', 'desc')
            ->get();

        return response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => $query->count(),
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

    public function showPropertyDetail(Request $request)
    {
        $user = $this->resolveActingUser($request);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $allowedLgaIds = $user->sectorLgaIds();
        $propertyDetail = PropertyList::find($request->id);

        if (! $propertyDetail) {
            return response()->json([
                'message' => 'Whoops! No record found.',
            ], 404);
        }

        if ($this->propertyOutsideAllowedLgas($propertyDetail, $allowedLgaIds)) {
            return $this->unauthorizedLgaResponse();
        }

        return response()->json([
            'data' => (new PropertyDetailResource($propertyDetail))->resolve(),
        ]);
    }

    public function savePropertyChanges(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'class' => 'required',
            'occupancy' => 'required',
            'age' => 'required',
            'propertyUse' => 'required',
            'landArea' => 'required',
            'address' => 'required',
            'propertyId' => 'required',
            'auth' => 'required',
            'propertyAddress' => 'nullable|string',
            'streetName' => 'nullable|string',
            'ward' => 'nullable|string',
            'zoneName' => 'nullable|string',
            'confirmedHouseNo' => 'nullable|string',
            'size' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'contactName' => 'nullable|string',
            'contactPhone' => 'nullable|string',
            'contactEmail' => 'nullable|email',
            'contactAddress' => 'nullable|string',
            'businessName1' => 'nullable|string',
            'businessName2' => 'nullable|string',
            'power' => 'nullable',
            'water' => 'nullable',
            'borehole' => 'nullable',
            'storey' => 'nullable',
            'imageUrl' => 'nullable|string',
        ], [
            'name.required' => 'Enter property name',
            'class.required' => 'Property classification is required',
            'occupancy.required' => 'Occupancy is required',
            'age.required' => 'What is the building age',
            'propertyUse.required' => 'Property use is required',
            'landArea.required' => 'Land area is required',
            'address.required' => 'Enter address',
            'auth.required' => '',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        try {
            $user = User::find($request->auth);
            if (empty($user)) {
                return $this->actingUserNotFoundResponse();
            }

            $allowedLgaIds = $user->sectorLgaIds();
            $propertyDetail = PropertyList::find($request->propertyId);

            if (! $propertyDetail) {
                return response()->json([
                    'message' => 'Whoops! No record found.',
                ], 404);
            }

            if ($this->propertyOutsideAllowedLgas($propertyDetail, $allowedLgaIds)) {
                return $this->unauthorizedLgaResponse();
            }
            $pavCode = $this->_getPavCode($request->propertyUse);
            if (empty($pavCode)) {
                return response()->json([
                    'message' => 'Whoops! No matching property use / PAV record found.',
                ], 404);
            }
            $chargeRate = ChargeRate::find($request->occupancy);
            $classRecord = PropertyClassification::find($request->class);

            $previousPropertyUse = trim((string) ($propertyDetail->property_use ?? ''));
            $newPropertyUse = trim((string) ($request->propertyUse ?? ''));

            $streetName = trim((string) ($request->streetName ?? ''));
            $confirmedHouseNo = trim((string) ($request->confirmedHouseNo ?? ''));
            $address = trim((string) ($request->address ?? ''));
            $propertyAddress = trim((string) ($request->propertyAddress ?? $address));

            $propertyDetail->property_name = $request->name ?? '';
            $propertyDetail->class_id = $request->class ?? '';
            $propertyDetail->class_name = $classRecord->class_name ?? $propertyDetail->class_name;
            $propertyDetail->building_age = $request->age ?? '';
            $propertyDetail->occupant = $request->occupancy ?? '';
            $propertyDetail->property_use = $request->propertyUse ?? '';
            $propertyDetail->property_type = $request->propertyUse ?? '';
            $propertyDetail->sync_word = $request->propertyUse ?? '';
            $propertyDetail->area = $request->landArea ?? '';
            $propertyDetail->image = $request->imageUrl ?? '';
            $propertyDetail->street_name = $streetName;
            $propertyDetail->confirmed_house_number = $confirmedHouseNo;
            $propertyDetail->address = $streetName;//$address;
            $propertyDetail->property_address = $streetName;//$propertyAddress !== '' ? $propertyAddress : $address;
            $propertyDetail->ward = $request->ward ?? '';
            $propertyDetail->sub_zone = $request->zoneName ?? '';
            $propertyDetail->size = $request->size ?? '';
            $propertyDetail->latitude = $request->latitude ?? '';
            $propertyDetail->longitude = $request->longitude ?? '';
            $propertyDetail->contact_name = $request->contactName ?? '';
            $propertyDetail->contact_phone = $request->contactPhone ?? '';
            $propertyDetail->contact_email = $request->contactEmail ?? '';
            $propertyDetail->contact_address = $request->contactAddress ?? '';
            $propertyDetail->business_name_1 = $request->businessName1 ?? '';
            $propertyDetail->business_name_2 = $request->businessName2 ?? '';
            $propertyDetail->power = $this->normalizeYesNoFlag($request->power);
            $propertyDetail->water = $this->normalizeYesNoFlag($request->water);
            $propertyDetail->borehole = $this->normalizeYesNoFlag($request->borehole);
            $propertyDetail->storey = $request->storey !== null && $request->storey !== '' ? (int) $request->storey : 0;
            $propertyDetail->pav_code = $pavCode->pav_code ?? '';
            $propertyDetail->cr = $request->occupancy ?? '';
            $propertyDetail->dep_id = $request->age ?? '';
            $propertyDetail->occupier = ! empty($chargeRate) ? $chargeRate->occupancy : '';
            $propertyDetail->save();

            if ($streetName !== '') {
                $this->syncStreetNameToBillings(
                    (string) $propertyDetail->building_code,
                    $streetName,
                    (int) $propertyDetail->id
                );
            }

            if ($newPropertyUse !== '' && strcasecmp($previousPropertyUse, $newPropertyUse) !== 0) {
                $this->syncCurrentYearBillingForPropertyUseChange(
                    (string) $propertyDetail->building_code,
                    $newPropertyUse,
                    (float) ($pavCode->annual_due ?? 0)
                );
            }

            $propertyDetail->refresh();
            $user = User::find($request->auth);
            if (! empty($user)) {
                $title = 'Property Changes';
                $narration = "{$user->name} made changes to a property with the building code: {$propertyDetail->building_code}";
                ActivityLog::LogActivity($title, $narration, $user->id);
            }

            return response()->json([
                'data' => (new PropertyDetailResource($propertyDetail))->resolve(),
                'message' => 'Changes saved!',
            ], 200);
        } catch (\Exception $exception) {
            Log::error('savePropertyChanges failed', [
                'property_id' => $request->propertyId,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Something went wrong'], 422);
        }

    }

    public function forceSynchronizeProperty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'buildingCode' => 'required',
            'auth' => 'required',
        ], [
            'buildingCode.required' => 'Building Code is required',
            'auth.required' => 'Authentication required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $result = $this->updatePropertyFromRemote($request->buildingCode);
        if (! $result['success']) {
            return response()->json([
                'errors' => $result['message'],
            ], $result['code'] ?? 422);
        }
        $user = User::find($request->auth);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $allowedLgaIds = $user->sectorLgaIds();
        $property = PropertyList::where('building_code', $request->buildingCode)->first();
        if ($property && $this->propertyOutsideAllowedLgas($property, $allowedLgaIds)) {
            return $this->unauthorizedLgaResponse();
        }

        if (! empty($user)) {
            $title = 'Property Force Synchronization';
            $narration = "{$user->name} forcefully synchronized a property with the building code: {$request->buildingCode}";
            ActivityLog::LogActivity($title, $narration, $user->id);
        }

        return response()->json([
            'data' => 'Property details updated successfully!',
            'property' => $result['data'],
        ], 200);
    }

    public function bulkForceSynchronization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attachment' => 'required|mimes:xlsx,xls|max:2048',
            'auth' => 'required',
            'header' => 'required',
        ], [
            'attachment.required' => 'Enter property name',
            'header.required' => 'Header is required',
            'auth.required' => '',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $path = $request->file('attachment')->store('uploads', 'public');

        if ($request->hasFile('attachment')) {
            BulkForceSynchronization::dispatch($path, $request->auth, $request->header)->onQueue('data_sync_queue');

            return response()->json(['message' => 'File uploaded successfully. Processing started.']);
        } else {
            return response()->json([
                'errors' => 'Choose a file to upload',
            ], 422);
        }

    }

    private function _getPavCode($propertyType)
    {
        return \App\Support\PropertyTypeMapper::findPav(
            propertyType: $propertyType,
            syncWord: $propertyType,
            propertyUse: $propertyType,
        );
    }

    /**
     * When property use changes, refresh current-year billings.bill_amount from PAV annual_due
     * and sync billings.property_use by building_code.
     */
    private function syncCurrentYearBillingForPropertyUseChange(
        string $buildingCode,
        string $propertyUse,
        float $annualDue,
    ): int {
        $buildingCode = trim($buildingCode);
        $propertyUse = trim($propertyUse);

        if ($buildingCode === '' || $propertyUse === '') {
            return 0;
        }

        $updated = Billing::query()
            ->where('building_code', $buildingCode)
            ->where('year', (int) date('Y'))
            ->update([
                'bill_amount' => $annualDue,
                'property_use' => $propertyUse,
            ]);

        if ($updated > 0) {
            Log::info('Updated current-year billing after property use change.', [
                'building_code' => $buildingCode,
                'property_use' => $propertyUse,
                'annual_due' => $annualDue,
                'year' => (int) date('Y'),
                'rows' => $updated,
            ]);
        }

        return $updated;
    }

    public function _getChargeRate($occupier, $landUse)
    {
        $normalizedClassName = trim($occupier);
        switch ($landUse) {
            case 1:
                if ($normalizedClassName == 'Owner_3rd_Party') {
                    return ChargeRate::whereRaw('occupancy LIKE ?', ['%Residential Property (Owner and 3rd Party)%'])->first();
                } elseif ($normalizedClassName == 'Third_party') {
                    return ChargeRate::whereRaw('occupancy LIKE ?', ['%Residential Property (without Owner in residence)%'])->first();

                } elseif ($normalizedClassName == 'Owner_occupier') {
                    return ChargeRate::whereRaw('occupancy LIKE ?', ['%Owner-occupied Residential Property%'])->first();

                } elseif ($normalizedClassName == 'Not_Known') {
                    return ChargeRate::whereRaw('occupancy LIKE ?', ['%Owner-occupied Residential Property%'])->first();

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

    public function _getClass($className)
    {
        $normalizedClassName = trim(strtolower($className));
        if ($normalizedClassName == 'government') {
            return PropertyClassification::where('class_name', 'Kogi State Govt.')->first();

        } elseif ($normalizedClassName == 'residential') {
            return PropertyClassification::where('class_name', 'Residential')->first();

        } elseif (in_array($normalizedClassName, ['recreational', 'religious', 'commercial'])) {
            return PropertyClassification::where('class_name', 'Commercial')->first();

        } elseif ($normalizedClassName == 'educational') {
            return PropertyClassification::where('class_name', 'Education (Private)')->first();

        } elseif ($normalizedClassName == 'health') {
            return PropertyClassification::where('class_name', 'Hospital')->first();

        } elseif ($normalizedClassName == 'open land') {
            return PropertyClassification::where('class_name', 'Vacant Properties & Open Land')->first();

        } else {
            return null;
        }
    }

    private function _getZoneCharacter($char): string
    {
        switch (substr($char, 0, 1)) {
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

    public function searchAllProperties(Request $request)
    {
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        if (! $keyword) {
            return response()->json([
                'errors' => 'No search term submitted',
            ], 404);
        }
        $user = User::find($request->actionedBy);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $lgaIds = $user->sectorLgaIds();

        return response()->json(['data' => PropertySearchResource::collection(PropertyList::searchAllProperties($keyword, $lgaIds))]);
    }

    public function searchAllPropertyException(Request $request)
    {
        $keyword = $request->keyword;
        $user = User::find($request->actionedBy);
        if (! $keyword) {
            return response()->json([
                'errors' => 'No search term submitted',
            ], 404);
        }

        // $propertyUse = explode(',', $user->sector);
        return response()->json([
            'data' => PropertySearchResource::collection(PropertyException::searchAllPropertyException($keyword)),
        ]);
    }

    public function deleteProperty(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'propertyId' => 'required',
                'actionedBy' => 'required',
            ],
            [
                'propertyId.required' => 'Missing info',
                'actionedBy.required' => 'Who is calling the shots?',
            ]
        );
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->messages(),
            ], 422);
        }
        $user = User::find($request->actionedBy);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $allowedLgaIds = $user->sectorLgaIds();
        $property = PropertyList::find($request->propertyId);
        if (empty($property)) {
            return response()->json([
                'errors' => 'No record found.',
            ], 404);
        }
        if ($this->propertyOutsideAllowedLgas($property, $allowedLgaIds)) {
            return $this->unauthorizedLgaResponse();
        }
        $bill = Billing::where('property_id', $request->propertyId)->first();
        if (! empty($bill)) {
            return response()->json([
                'errors' => "This property has a paid bill. It can't be deleted.",
            ], 422);
        }
        $property->delete();
        // log activity
        $user = User::find($request->actionedBy);
        if (! empty($user)) {
            $lga = Lga::find($property->lga_id);
            $class = PropertyClassification::find($property->class_id);
            $lgaName = ! empty($lga) ? $lga->lga_name : '-';
            $className = ! empty($class) ? $class->class_name : '-';
            $title = "Property {$property->building_code} deleted.";
            $narration = "{$user->name} deleted property with the building code:  {$property->building_code}. Details are as shown below:
           Billing Code: {$property->pav_code},
            LGA: {$lgaName}, Zone: {$property->sub_zone}, Ward: {$property->ward}, Class: {$className}, Property Use: {$property->property_use}";
            ActivityLog::LogActivity($title, $narration, $user->id);
        }

        return response()->json(['data' => 'Action successful'], 200);
    }

    public function showExemptedProperties(Request $request)
    {
        $user = $this->resolveActingUser($request);
        if (empty($user)) {
            return $this->actingUserNotFoundResponse();
        }

        $limit = $request->limit ?? 0;
        $skip = $request->skip ?? 0;
        $allowedLgaIds = $user->sectorLgaIds();
        $chargeIds = ChargeRate::where('rate', 0)->pluck('id')->toArray();
        $query = PropertyList::query()
            ->forAllowedLgas($allowedLgaIds)
            ->whereIn('cr', $chargeIds);

        $records = (clone $query)
            ->skip($skip)
            ->take($limit)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'list' => PropertyListResource::collection($records),
            'total' => $query->count(),
        ]);
    }

    private function normalizeYesNoFlag($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'yes', 'true', 'y'], true) ? 1 : 0;
    }
}

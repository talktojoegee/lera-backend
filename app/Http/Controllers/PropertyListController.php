<?php

namespace App\Http\Controllers;

use App\Http\Resources\PropertyListResource;
use App\Models\PropertyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PropertyListController extends Controller
{
    //

    public function __construct(){

    }


    public function showPropertyLists(Request $request){
        //$perPage = $request->query('perPage', 10); // Default to 10 items per page
        $data = PropertyList::query()
        ->orderBy('id', 'DESC')
        ->paginate(10);
        //return response()->json($data);
        return PropertyListResource::collection($data);
    }


    public function getPropertyList(Request $request)
    {
        // Default values
        $limit = (int) $request->query('limit', 10); // Number of records per page
        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $limit; // Calculate offset

        // Base query
        $query = PropertyList::join('lgas as l', 'property_lists.lga_id', '=', 'l.id')
            //->join('employees as e', 'orders.EmployeeID', '=', 'e.id')
            //->join('shippers as s', 'orders.ShipperID', '=', 's.id')
            ->select(
                'property_lists.*',
                //DB::raw("DATE_FORMAT(orders.OrderDate, '%d-%b-%y') AS ODate"),
                //'c.CustomerName',
                //DB::raw("CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName"),
                'l.lga_name'
            );

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
    }
}

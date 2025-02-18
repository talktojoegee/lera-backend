<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
//use Tymon\JWTAuth\Http\Middleware\Check as JWTMiddleware;


Route::options('{any}', function () {
    return response()->json(['status' => 'OK'], 200);
})->where('any', '.*');

Route::post('/register',[\App\Http\Controllers\AuthenticationController::class, 'register']);
Route::post('/authenticate',[\App\Http\Controllers\AuthenticationController::class, 'authenticate']);
Route::post('/logout', [\App\Http\Controllers\AuthenticationController::class, 'logout']);


/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::group(['middleware' => 'api'], function(){
    Route::post('/lga/new', [\App\Http\Controllers\LGAController::class, 'createLGA']);
    Route::get('/lga/all', [\App\Http\Controllers\LGAController::class, 'showAllLGAs']);

    Route::post('/zone/new', [\App\Http\Controllers\ZoneController::class, 'createZone']);
    Route::get('/zone/all', [\App\Http\Controllers\ZoneController::class, 'showAllZones']);

    #Relief settings
    Route::post('/relief/new', [\App\Http\Controllers\ReliefController::class, 'storeReliefSettings']);
    Route::get('/relief/all', [\App\Http\Controllers\ReliefController::class, 'showReliefSetup']);
    Route::get('/relief/all/{type}', [\App\Http\Controllers\ReliefController::class, 'showReliefSetupByType']);

    #Property title settings
    Route::post('/property-title/new', [\App\Http\Controllers\PropertyTitleController::class, 'storePropertyTitle']);
    Route::get('/property-title/all', [\App\Http\Controllers\PropertyTitleController::class, 'showAllPropertyTitles']);

    #area-office settings
    Route::post('/area-office/new', [\App\Http\Controllers\AreaOfficeController::class, 'storeAreaOffice']);
    Route::get('/area-office/all', [\App\Http\Controllers\AreaOfficeController::class, 'showAllAreaOffices']);

    #Property classification settings
    Route::post('/property-classification/new', [\App\Http\Controllers\PropertyClassificationController::class, 'storeClass']);
    Route::get('/property-classification/all', [\App\Http\Controllers\PropertyClassificationController::class, 'showAllPropertyClassifications']);

    #Depreciation
    Route::post('/depreciation/new', [\App\Http\Controllers\DepreciationController::class, 'createDepreciation']);
    Route::get('/depreciation/all', [\App\Http\Controllers\DepreciationController::class, 'showAllDepreciations']);

    #Charge rate
    Route::post('/charge-rate/new', [\App\Http\Controllers\ChargeRateController::class, 'createChargeRate']);
    Route::get('/charge-rate/all', [\App\Http\Controllers\ChargeRateController::class, 'showAllChargeRates']);

    #PAV
    Route::post('/property-assessment-value/new', [\App\Http\Controllers\PropertyAssessmentValueController::class, 'storePAV']);
    Route::get('/property-assessment-value/all/{limit}/{skip}', [\App\Http\Controllers\PropertyAssessmentValueController::class, 'showAllPAVs']);
    Route::post('/property-assessment-value/update', [\App\Http\Controllers\PropertyAssessmentValueController::class, 'updatePAV']);
    Route::get('/sectors/distinct', [\App\Http\Controllers\PropertyAssessmentValueController::class, 'showDistinctSectors']);

    #Owners
    Route::post('/owners/new', [\App\Http\Controllers\OwnersController::class, 'storeOwner']);
    Route::get('/owners/all', [\App\Http\Controllers\OwnersController::class, 'showAllOwners']);
    Route::get('/owners/{kgtin}', [\App\Http\Controllers\OwnersController::class, 'showOwnerByKGTin']);
    Route::post('/owners/save-changes', [\App\Http\Controllers\OwnersController::class, 'saveOwnerChanges']);



    Route::get('/sync-data/{lgaId}', [\App\Http\Controllers\RemoteController::class, 'showBuildingsByLGAId']);
    Route::get('/synchronization-report/{limit}/{skip}', [\App\Http\Controllers\RemoteController::class, 'showSyncReport']);


    Route::post('/billing/retrieve', [\App\Http\Controllers\BillingController::class, 'retrieveBills']);
    Route::post('/billing/process', [\App\Http\Controllers\BillingController::class, 'processBill']);

    Route::post('/billing/make-payment', [\App\Http\Controllers\PaymentController::class, 'handlePaymentRequest']);

    Route::get('/billing/chart-summary/{year}', [\App\Http\Controllers\BillingController::class, 'showBillDataOnDashboard']);
    Route::get('/billing/property-distribution/{year}', [\App\Http\Controllers\BillingController::class, 'showPropertyDistributionByZones']);
    Route::get('/property/distribution', [\App\Http\Controllers\BillingController::class, 'showPropertyDistributionByLGA']);
    Route::get('/dashboard/statistics/{year}', [\App\Http\Controllers\BillingController::class, 'showDashboardStatistics']);
    Route::get('/billing/outstanding-bills/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showOutstandingBills']);

    Route::get('/billing/bills/{user}/{limit}/{skip}/{status}', [\App\Http\Controllers\BillingController::class, 'showBills']);

    Route::get('/billing/special-interest-bills/{user}/{limit}/{skip}/{status}', [\App\Http\Controllers\BillingController::class, 'showSpecialInterestBills']);
    Route::get('/billing/paid-special-interest/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showPaidSpecialInterestBills']);
    Route::get('/billing/all-pending-bills/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showAllPendingBills']);

    Route::get('/billing/returned-bills/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showReturnedBills']);
    Route::get('/billing/returned-special-interest-bills/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showSpecialInterestReturnedBills']);
    //Route::get('/billing/outstanding-bills', [\App\Http\Controllers\BillingController::class, 'showOutstandingBills']);

    Route::get('/billing/paid/{user}/{limit}/{skip}', [\App\Http\Controllers\BillingController::class, 'showPaidBills']);

    Route::get('/billing/detail/{url}', [\App\Http\Controllers\BillingController::class, 'showBillDetails']);
    Route::post('billing/action-bill', [\App\Http\Controllers\BillingController::class, 'actionBill']);
    Route::post('billing/update-bill-changes', [\App\Http\Controllers\BillingController::class, 'updateBillChanges']);
    Route::get('billing/rollback/{year}', [\App\Http\Controllers\BillingController::class, 'rollbackBill']);
    Route::post('billing/toggle-bill-type', [\App\Http\Controllers\BillingController::class, 'toggleBillType']);
    Route::post('billing/bills/bulk-action', [\App\Http\Controllers\BillingController::class, 'handleBillBulkAction']);



    Route::get('/property-list/all/{limit}/{skip}', [\App\Http\Controllers\PropertyListController::class, 'getPropertyList']);
    Route::get('/property-exception/all/{limit}/{skip}', [\App\Http\Controllers\PropertyListController::class, 'getPropertyExceptionList']);
    Route::get('/property/{id}', [\App\Http\Controllers\PropertyListController::class, 'showPropertyDetail']);
    //Route::get('/property-list/all', [\App\Http\Controllers\PropertyListController::class, 'showPropertyLists']);



    Route::get('/chart-record/{year}', [\App\Http\Controllers\BillingController::class, 'chartTest']);

    Route::get('download/attachment/{slug}', [App\Http\Controllers\ObjectionController::class, 'downloadAttachment'] );

    Route::post('/objection/new', [\App\Http\Controllers\ObjectionController::class, 'handleNewObjection']);
    Route::get('/objection/detail/{requestId}', [\App\Http\Controllers\ObjectionController::class, 'showObjectionDetail']);
    Route::get('/objection/all/{status}/{limit}/{skip}', [\App\Http\Controllers\ObjectionController::class, 'showObjectionListByStatus']);

    Route::post('objection/action-objection', [\App\Http\Controllers\ObjectionController::class, 'actionObjection']);




    #Export operations
    Route::get('/export-bills', [\App\Http\Controllers\ExportController::class, 'exportExcel']);


    #Users
    Route::get('/users/all/{type}/{limit}/{skip}', [\App\Http\Controllers\UserController::class, 'showAllUsers']);
    Route::post('add-new-user', [\App\Http\Controllers\UserController::class, 'storeUser']);

    #Roles
    Route::post('/access/permissions/new', [\App\Http\Controllers\RolePermissionController::class, 'createPermission']);
    Route::get('/access/permissions/all', [\App\Http\Controllers\RolePermissionController::class, 'showAllPermissions']);

    #Role-permission assignment
    Route::post('/access/roles-permission/new', [\App\Http\Controllers\RolePermissionController::class, 'assignPermissionToRole']);
    Route::get('/access/roles-permission/all', [\App\Http\Controllers\RolePermissionController::class, 'showAllRolePermissions']);


    #Permission
    Route::post('/access/roles/new', [\App\Http\Controllers\RolePermissionController::class, 'createRole']);
    Route::get('/access/roles/all', [\App\Http\Controllers\RolePermissionController::class, 'showAllRoles']);

    //Route::get('/access/all', [\App\Http\Controllers\LGAController::class, 'showAllLGAs']);




});
Route::middleware([\App\Http\Middleware\JsonApiMiddleware::class])->group( function(){


});

//

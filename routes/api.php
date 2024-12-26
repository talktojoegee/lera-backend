<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware([\App\Http\Middleware\JsonApiMiddleware::class])->group( function(){
    Route::post('/lga/new', [\App\Http\Controllers\LGAController::class, 'createLGA']);
    Route::get('/lga/all', [\App\Http\Controllers\LGAController::class, 'showAllLGAs']);

    #Relief settings
    Route::post('/relief/new', [\App\Http\Controllers\ReliefController::class, 'storeReliefSettings']);
    Route::get('/relief/all', [\App\Http\Controllers\ReliefController::class, 'showReliefSetup']);

    #Property title settings
    Route::post('/property-title/new', [\App\Http\Controllers\PropertyTitleController::class, 'storePropertyTitle']);
    Route::get('/property-title/all', [\App\Http\Controllers\PropertyTitleController::class, 'showAllPropertyTitles']);

    #area-office settings
    Route::post('/area-office/new', [\App\Http\Controllers\AreaOfficeController::class, 'storeAreaOffice']);
    Route::get('/area-office/all', [\App\Http\Controllers\AreaOfficeController::class, 'showAllAreaOffices']);

    #Property classification settings
    Route::post('/property-classification/new', [\App\Http\Controllers\PropertyClassificationController::class, 'storeClass']);
    Route::get('/property-classification/all', [\App\Http\Controllers\PropertyClassificationController::class, 'showAllPropertyClassifications']);
});
//

<?php

use Illuminate\Support\Facades\Route;
Route::get('/migration', function(){
    \Illuminate\Support\Facades\Artisan::call('config:cache');
    return dd('success');
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function() { \Illuminate\Support\Facades\Artisan::call('cache:clear'); });

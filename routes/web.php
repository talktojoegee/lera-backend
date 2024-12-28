<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function() { \Illuminate\Support\Facades\Artisan::call('cache:clear'); });

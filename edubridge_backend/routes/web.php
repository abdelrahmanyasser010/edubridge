<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'edubridge-backend',
    ]);
});

Route::get('/', function () {
    return view('welcome');
});

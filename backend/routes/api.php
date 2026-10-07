<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'StayHub Laravel API is working',
    ]);
});

Route::get('/me', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
})->middleware('auth:sanctum');

// TEMP-M2-WIRING
require __DIR__ . '/api/booking.php';


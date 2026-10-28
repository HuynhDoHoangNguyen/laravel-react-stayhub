<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'StayHub Laravel API is working',
    ]);
});

Route::get('/me', [AuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'account.active']);

require __DIR__ . '/api/billing.php';
require __DIR__ . '/api/reviews.php';
require __DIR__ . '/api/profile.php';

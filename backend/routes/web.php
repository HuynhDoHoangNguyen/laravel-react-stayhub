<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EnsureRegistrationGuest;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register'])
    ->middleware([ThrottleRegistration::class, EnsureRegistrationGuest::class]);
// Remains available to guests and non-ACTIVE sessions so they can clear cookies.
Route::post('/logout', [AuthController::class, 'logout']);

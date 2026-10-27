<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
// Remains available to guests and non-ACTIVE sessions so they can clear cookies.
Route::post('/logout', [AuthController::class, 'logout']);

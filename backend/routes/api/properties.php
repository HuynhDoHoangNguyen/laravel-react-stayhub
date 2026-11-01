<?php

use App\Http\Controllers\Api\Host\HostPropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/host/properties', [HostPropertyController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', 'role:HOST']);

<?php

use App\Http\Controllers\Commission\HostCommissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->prefix('host/commissions')->group(function () {
    Route::get('/', [HostCommissionController::class, 'index']);
    Route::get('/{id}', [HostCommissionController::class, 'show']);
    Route::post('/{id}/remittances', [HostCommissionController::class, 'submitRemittance']);
});

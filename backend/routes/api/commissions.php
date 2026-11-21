<?php

use App\Http\Controllers\Commission\AdminCommissionController;
use App\Http\Controllers\Commission\HostCommissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->prefix('host/commissions')->group(function () {
    Route::get('/dashboard/financial-summary', [HostCommissionController::class, 'financialSummary']);
    Route::get('/', [HostCommissionController::class, 'index']);
    Route::get('/{id}', [HostCommissionController::class, 'show']);
    Route::post('/{id}/remittances', [HostCommissionController::class, 'submitRemittance']);
});

Route::middleware(['auth:sanctum', 'account.active'])->prefix('admin')->group(function () {
    Route::get('/dashboard/commission-summary', [AdminCommissionController::class, 'commissionSummary']);
    Route::get('/commissions', [AdminCommissionController::class, 'commissions']);
    Route::get('/commission-remittances', [AdminCommissionController::class, 'remittances']);
    Route::post('/commission-remittances/{id}/verify', [AdminCommissionController::class, 'verify']);
    Route::post('/commission-remittances/{id}/reject', [AdminCommissionController::class, 'reject']);
});

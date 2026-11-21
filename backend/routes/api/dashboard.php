<?php

use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Statistics\AdminStatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/dashboard', [AdminDashboardController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active'])->name('admin.dashboard.show');

Route::get('/admin/statistics', [AdminStatisticsController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active'])->name('admin.statistics.show');

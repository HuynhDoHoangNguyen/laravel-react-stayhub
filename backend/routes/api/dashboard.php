<?php

use App\Http\Controllers\Dashboard\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/dashboard', [AdminDashboardController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active'])->name('admin.dashboard.show');

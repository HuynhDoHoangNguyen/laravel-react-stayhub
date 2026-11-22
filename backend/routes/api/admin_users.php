<?php

use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->prefix('admin/users')->group(function () {
    Route::get('/', [AdminUserController::class, 'index']);
    Route::post('/{id}/approve-host', [AdminUserController::class, 'approveHost']);
    Route::patch('/{id}/status', [AdminUserController::class, 'updateStatus']);
});

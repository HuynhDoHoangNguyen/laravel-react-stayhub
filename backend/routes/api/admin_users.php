<?php

use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])->group(function () {
    Route::get('/admin/user-management', [AdminUserController::class, 'index'])
        ->name('admin.user-management.index');
    Route::post('/admin/users/{id}/approve-host', [AdminUserController::class, 'approveHost'])
        ->name('admin.hosts.approve');
});

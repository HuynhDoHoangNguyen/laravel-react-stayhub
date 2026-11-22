<?php

use App\Http\Controllers\UserManagement\AdminCustomerController;
use App\Http\Controllers\UserManagement\AdminHostController;
use App\Http\Controllers\UserManagement\AccountStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/users', [AdminCustomerController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.customers.index');

Route::get('/admin/users/{user}', [AdminCustomerController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.customers.show');

Route::patch('/admin/users/{user}/status', [AccountStatusController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.users.status.update');

Route::get('/admin/hosts', [AdminHostController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.hosts.index');

Route::get('/admin/hosts/{host}', [AdminHostController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.hosts.show');

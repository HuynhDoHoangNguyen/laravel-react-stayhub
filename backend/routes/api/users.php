<?php

use App\Http\Controllers\UserManagement\AdminCustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/users', [AdminCustomerController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.customers.index');

Route::get('/admin/users/{user}', [AdminCustomerController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.customers.show');

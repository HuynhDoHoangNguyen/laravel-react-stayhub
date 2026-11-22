<?php

use App\Http\Controllers\Booking\AdminBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/bookings', [AdminBookingController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active'])
    ->name('admin.bookings.index');

Route::get('/admin/bookings/filter-options', [AdminBookingController::class, 'filterOptions'])
    ->middleware(['auth:sanctum', 'account.active'])
    ->name('admin.bookings.filter-options');

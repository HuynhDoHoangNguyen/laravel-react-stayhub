<?php

use App\Http\Controllers\Api\Customer\BookingController as CustomerBookingController;
use App\Http\Controllers\Api\Host\BookingServiceController as HostBookingServiceController;
use App\Http\Controllers\Api\Host\HostBookingController;
use App\Http\Controllers\Api\Host\ServiceController as HostServiceController;
use App\Http\Controllers\Api\Public\BookingPreviewController;
use App\Http\Controllers\Api\Public\RoomAvailabilityController;
use App\Http\Controllers\Api\Public\RoomSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Booking Module Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('public')->group(function () {
    // Search route MUST be declared before /rooms/{room}
    Route::get('/rooms/search', [RoomSearchController::class, 'index']);
    Route::get('/rooms/{room}/availability', [RoomAvailabilityController::class, 'show'])->whereNumber('room');

    Route::post('/bookings/preview', [BookingPreviewController::class, 'preview'])->middleware('throttle:60,1');
});

// Customer routes
Route::prefix('customer')->middleware(['auth:sanctum', 'role:CUSTOMER'])->group(function () {
    Route::post('/bookings', [CustomerBookingController::class, 'store']);
    Route::get('/bookings', [CustomerBookingController::class, 'index']);
    Route::get('/bookings/{booking}', [CustomerBookingController::class, 'show'])->whereNumber('booking');
    Route::post('/bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->whereNumber('booking');
    Route::get('/bookings/{booking}/services', [CustomerBookingController::class, 'services'])->whereNumber('booking');
});

// Host routes
Route::prefix('host')->middleware(['auth:sanctum', 'role:HOST'])->group(function () {
    // Property Services
    Route::get('/services', [HostServiceController::class, 'index']);
    Route::post('/services', [HostServiceController::class, 'store']);
    Route::put('/services/{service}', [HostServiceController::class, 'update'])->whereNumber('service');
    Route::patch('/services/{service}/status', [HostServiceController::class, 'status'])->whereNumber('service');

    // Host Bookings
    Route::get('/bookings', [HostBookingController::class, 'index']);
    Route::get('/bookings/{booking}', [HostBookingController::class, 'show'])->whereNumber('booking');
    Route::post('/bookings/{booking}/confirm', [HostBookingController::class, 'confirm'])->whereNumber('booking');
    Route::post('/bookings/{booking}/reject', [HostBookingController::class, 'reject'])->whereNumber('booking');
    Route::post('/bookings/{booking}/check-in', [HostBookingController::class, 'checkIn'])->whereNumber('booking');
    Route::post('/bookings/{booking}/check-out', [HostBookingController::class, 'checkOut'])->whereNumber('booking');

    // Booking Services Management
    Route::get('/bookings/{booking}/services', [HostBookingServiceController::class, 'index'])->whereNumber('booking');
    Route::post('/bookings/{booking}/services', [HostBookingServiceController::class, 'store'])->whereNumber('booking');
    Route::put('/bookings/{booking}/services/{bookingService}', [HostBookingServiceController::class, 'update'])
        ->whereNumber('booking')
        ->whereNumber('bookingService');
    Route::delete('/bookings/{booking}/services/{bookingService}', [HostBookingServiceController::class, 'destroy'])
        ->whereNumber('booking')
        ->whereNumber('bookingService');
});

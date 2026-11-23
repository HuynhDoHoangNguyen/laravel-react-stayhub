<?php

use App\Http\Controllers\Catalog\PublicPropertyController;
use App\Http\Controllers\Catalog\AdminPropertyController;
use App\Http\Controllers\Catalog\AdminAmenityController;
use App\Http\Controllers\Catalog\PublicRoomController;
use App\Http\Controllers\Catalog\HostPropertyController;
use App\Http\Controllers\Catalog\HostPropertyImageController;
use App\Http\Controllers\Catalog\HostRoomTypeController;
use App\Http\Controllers\Catalog\HostRoomController;
use App\Http\Controllers\Catalog\HostRoomMaintenanceController;
use App\Http\Controllers\Catalog\HostRoomAmenityController;
use App\Http\Middleware\EnsureHostIsApproved;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Catalog\HostRoomImageController;

Route::patch('/host/rooms/{room}/images/{image}/primary', [HostRoomImageController::class, 'primary'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::delete('/host/rooms/{room}/images/{image}', [HostRoomImageController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/rooms/{room}/images', [HostRoomImageController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::post('/host/rooms/{room}/images', [HostRoomImageController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/public/rooms', [PublicRoomController::class, 'index']);
Route::get('/public/rooms/filter-options', [PublicRoomController::class, 'filterOptions']);
Route::get('/public/rooms/{room}', [PublicRoomController::class, 'show']);

Route::get('/public/properties', [PublicPropertyController::class, 'index']);
Route::get('/public/properties/{property}', [PublicPropertyController::class, 'show'])->whereNumber('property');

Route::get('/admin/properties', [AdminPropertyController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.properties.index');

Route::get('/admin/properties/{property}', [AdminPropertyController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->name('admin.properties.show');

Route::prefix('admin/amenities')->name('admin.amenities.')
    ->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->controller(AdminAmenityController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{amenity}', 'show')->name('show');
        Route::post('/', 'store')->name('store');
        Route::put('/{amenity}', 'update')->name('update');
    });

Route::get('/host/properties', [HostPropertyController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::post('/host/properties', [HostPropertyController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::put('/host/properties/{property}', [HostPropertyController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::patch('/host/properties/{property}/status', [HostPropertyController::class, 'changeStatus'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/properties/{property}', [HostPropertyController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/properties/{property}/images', [HostPropertyImageController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::post('/host/properties/{property}/images', [HostPropertyImageController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::patch('/host/properties/{property}/images/{image}/primary', [HostPropertyImageController::class, 'primary'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::delete('/host/properties/{property}/images/{image}', [HostPropertyImageController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/properties/{property}/room-types', [HostRoomTypeController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/properties/{property}/rooms', [HostRoomController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::post('/host/properties/{property}/rooms', [HostRoomController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::patch('/host/rooms/{room}/status', [HostRoomController::class, 'changeStatus'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::put('/host/rooms/{room}', [HostRoomController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/rooms/{room}/amenities', [HostRoomAmenityController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::put('/host/rooms/{room}/amenities/{amenity}', [HostRoomAmenityController::class, 'assign'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::delete('/host/rooms/{room}/amenities/{amenity}', [HostRoomAmenityController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/rooms/{room}', [HostRoomController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::get('/host/rooms/{room}/maintenances', [HostRoomMaintenanceController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::post('/host/rooms/{room}/maintenances', [HostRoomMaintenanceController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::put('/host/maintenances/{maintenance}', [HostRoomMaintenanceController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::delete('/host/maintenances/{maintenance}', [HostRoomMaintenanceController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);
Route::patch('/host/maintenances/{maintenance}/status', [HostRoomMaintenanceController::class, 'changeStatus'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::post('/host/properties/{property}/room-types', [HostRoomTypeController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::put('/host/room-types/{roomType}', [HostRoomTypeController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

Route::delete('/host/room-types/{roomType}', [HostRoomTypeController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active', EnsureHostIsApproved::class]);

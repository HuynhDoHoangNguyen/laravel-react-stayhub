<?php

use App\Http\Controllers\Catalog\PublicPropertyController;
use App\Http\Controllers\Catalog\PublicRoomController;
use App\Http\Controllers\Catalog\HostPropertyController;
use App\Http\Controllers\Catalog\HostPropertyImageController;
use App\Http\Controllers\Catalog\HostRoomTypeController;
use App\Http\Controllers\Catalog\HostRoomController;
use App\Http\Controllers\Catalog\HostRoomAmenityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Catalog\HostRoomImageController;

Route::patch('/host/rooms/{room}/images/{image}/primary', [HostRoomImageController::class, 'primary'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::delete('/host/rooms/{room}/images/{image}', [HostRoomImageController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/rooms/{room}/images', [HostRoomImageController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::post('/host/rooms/{room}/images', [HostRoomImageController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/public/rooms', [PublicRoomController::class, 'index']);
Route::get('/public/rooms/filter-options', [PublicRoomController::class, 'filterOptions']);
Route::get('/public/rooms/{room}', [PublicRoomController::class, 'show']);

Route::get('/public/properties', [PublicPropertyController::class, 'index']);
Route::get('/public/properties/{property}', [PublicPropertyController::class, 'show'])->whereNumber('property');

Route::get('/host/properties', [HostPropertyController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::post('/host/properties', [HostPropertyController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::put('/host/properties/{property}', [HostPropertyController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::patch('/host/properties/{property}/status', [HostPropertyController::class, 'changeStatus'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/properties/{property}', [HostPropertyController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/properties/{property}/images', [HostPropertyImageController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::post('/host/properties/{property}/images', [HostPropertyImageController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::patch('/host/properties/{property}/images/{image}/primary', [HostPropertyImageController::class, 'primary'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::delete('/host/properties/{property}/images/{image}', [HostPropertyImageController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/properties/{property}/room-types', [HostRoomTypeController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/properties/{property}/rooms', [HostRoomController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::post('/host/properties/{property}/rooms', [HostRoomController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::patch('/host/rooms/{room}/status', [HostRoomController::class, 'changeStatus'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::put('/host/rooms/{room}', [HostRoomController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/rooms/{room}/amenities', [HostRoomAmenityController::class, 'index'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::put('/host/rooms/{room}/amenities/{amenity}', [HostRoomAmenityController::class, 'assign'])
    ->middleware(['auth:sanctum', 'account.active']);
Route::delete('/host/rooms/{room}/amenities/{amenity}', [HostRoomAmenityController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::get('/host/rooms/{room}', [HostRoomController::class, 'show'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::post('/host/properties/{property}/room-types', [HostRoomTypeController::class, 'store'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::put('/host/room-types/{roomType}', [HostRoomTypeController::class, 'update'])
    ->middleware(['auth:sanctum', 'account.active']);

Route::delete('/host/room-types/{roomType}', [HostRoomTypeController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'account.active']);

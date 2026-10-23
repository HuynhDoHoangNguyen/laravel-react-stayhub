<?php

use App\Http\Controllers\Review\CustomerReviewController;
use App\Http\Controllers\Review\HostReviewController;
use App\Http\Controllers\Review\PublicReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Review & Rating Module Routes (TV3)
|--------------------------------------------------------------------------
*/

// Public Reviews
Route::get('/public/properties/{property}/reviews', [PublicReviewController::class, 'index'])
    ->name('public.properties.reviews.index');

// Authenticated Reviews (Customer & Host)
Route::middleware('auth:sanctum')->group(function () {
    // Customer Reviews
    Route::post('/customer/bookings/{booking}/review', [CustomerReviewController::class, 'store'])
        ->name('customer.bookings.review.store');
    Route::get('/customer/reviews', [CustomerReviewController::class, 'index'])
        ->name('customer.reviews.index');

    // Host Reviews
    Route::get('/host/reviews', [HostReviewController::class, 'index'])
        ->name('host.reviews.index');
});


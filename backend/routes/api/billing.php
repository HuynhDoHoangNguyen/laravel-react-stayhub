<?php

use App\Http\Controllers\Invoice\HostInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing & Invoice Module Routes (TV3)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/host/bookings/{booking}/invoice', [HostInvoiceController::class, 'store'])
        ->name('host.bookings.invoice.store');
});

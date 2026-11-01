<?php

use App\Http\Controllers\Invoice\CustomerInvoiceController;
use App\Http\Controllers\Invoice\HostInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing & Invoice Module Routes (TV3)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    // Host Invoices
    Route::get('/host/invoices', [HostInvoiceController::class, 'index'])
        ->name('host.invoices.index');
    Route::post('/host/bookings/{booking}/invoice', [HostInvoiceController::class, 'store'])
        ->name('host.bookings.invoice.store');
    Route::get('/host/invoices/{invoice}', [HostInvoiceController::class, 'show'])
        ->name('host.invoices.show');

    // Customer Invoices
    Route::get('/customer/invoices', [CustomerInvoiceController::class, 'index'])
        ->name('customer.invoices.index');
    Route::get('/customer/invoices/{invoice}', [CustomerInvoiceController::class, 'show'])
        ->name('customer.invoices.show');
});

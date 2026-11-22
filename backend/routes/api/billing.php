<?php

use App\Http\Controllers\Invoice\CustomerInvoiceController;
use App\Http\Controllers\Invoice\AdminInvoiceController;
use App\Http\Controllers\Invoice\HostInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing & Invoice Module Routes (TV3)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('/admin/invoices/filter-options', [AdminInvoiceController::class, 'filterOptions'])
        ->name('admin.invoices.filter-options');
    Route::get('/admin/invoices', [AdminInvoiceController::class, 'index'])
        ->name('admin.invoices.index');

    // Host Invoices
    Route::get('/host/invoices', [HostInvoiceController::class, 'index'])
        ->middleware(\App\Http\Middleware\EnsureHostIsApproved::class)
        ->name('host.invoices.index');
    Route::get('/host/invoices/eligible-bookings', [HostInvoiceController::class, 'eligibleBookings'])
        ->middleware(['role:HOST', \App\Http\Middleware\EnsureHostIsApproved::class])
        ->name('host.invoices.eligible-bookings');
    Route::get('/host/bookings/{booking}/invoice-context', [HostInvoiceController::class, 'bookingContext'])
        ->middleware(\App\Http\Middleware\EnsureHostIsApproved::class)
        ->name('host.bookings.invoice.context');
    Route::post('/host/bookings/{booking}/invoice', [HostInvoiceController::class, 'store'])
        ->middleware(\App\Http\Middleware\EnsureHostIsApproved::class)
        ->name('host.bookings.invoice.store');
    Route::get('/host/invoices/{invoice}', [HostInvoiceController::class, 'show'])
        ->middleware(\App\Http\Middleware\EnsureHostIsApproved::class)
        ->name('host.invoices.show');
    Route::patch('/host/invoices/{invoice}/payment-status', [HostInvoiceController::class, 'updatePaymentStatus'])
        ->middleware(\App\Http\Middleware\EnsureHostIsApproved::class)
        ->name('host.invoices.payment-status.update');

    // Customer Invoices
    Route::get('/customer/invoices', [CustomerInvoiceController::class, 'index'])
        ->name('customer.invoices.index');
    Route::get('/customer/invoices/{invoice}', [CustomerInvoiceController::class, 'show'])
        ->name('customer.invoices.show');
});

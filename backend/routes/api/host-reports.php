<?php

use App\Http\Controllers\HostReport\CustomerHostReportController;
use App\Http\Controllers\HostReport\AdminHostReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active', 'role:CUSTOMER'])
    ->group(function (): void {
        Route::get('/customer/bookings/{booking}/host-report', [CustomerHostReportController::class, 'show'])
            ->whereNumber('booking')->name('customer.bookings.host-report.show');
        Route::post('/customer/bookings/{booking}/host-report', [CustomerHostReportController::class, 'store'])
            ->whereNumber('booking')->middleware('throttle:5,1,host-report:')
            ->name('customer.bookings.host-report.store');
    });

Route::prefix('admin/host-reports')->middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
    ->group(function (): void {
        Route::get('/', [AdminHostReportController::class, 'index'])->name('admin.host-reports.index');
        Route::get('/{report}', [AdminHostReportController::class, 'show'])
            ->whereNumber('report')->name('admin.host-reports.show');
        Route::get('/{report}/evidence', [AdminHostReportController::class, 'evidence'])
            ->whereNumber('report')->name('admin.host-reports.evidence');
        Route::patch('/{report}/decision', [AdminHostReportController::class, 'update'])
            ->whereNumber('report')->name('admin.host-reports.decision');
    });

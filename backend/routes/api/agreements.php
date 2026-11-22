<?php

use App\Http\Controllers\Agreement\HostPartnerAgreementController;
use App\Http\Middleware\EnsureRegistrationGuest;
use App\Http\Middleware\ThrottleRegistration;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/partner-agreements/current', [HostPartnerAgreementController::class, 'publicCurrent']);
    Route::post('/host-register', [HostPartnerAgreementController::class, 'registerHost'])
        ->middleware(['web', ThrottleRegistration::class, EnsureRegistrationGuest::class]);
});

Route::middleware(['auth:sanctum', 'account.active'])->prefix('host/partner-agreements')->group(function () {
    Route::get('/current', [HostPartnerAgreementController::class, 'current']);
    Route::get('/acceptances', [HostPartnerAgreementController::class, 'acceptances']);
    Route::post('/{id}/accept', [HostPartnerAgreementController::class, 'accept']);
});

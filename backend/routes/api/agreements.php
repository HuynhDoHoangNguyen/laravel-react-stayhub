<?php

use App\Http\Controllers\Agreement\HostPartnerAgreementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->prefix('host/partner-agreements')->group(function () {
    Route::get('/current', [HostPartnerAgreementController::class, 'current']);
    Route::get('/acceptances', [HostPartnerAgreementController::class, 'acceptances']);
    Route::post('/{id}/accept', [HostPartnerAgreementController::class, 'accept']);
});

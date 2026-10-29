<?php

use App\Http\Controllers\Catalog\PublicPropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/public/properties', [PublicPropertyController::class, 'index']);
Route::get('/public/properties/{property}', [PublicPropertyController::class, 'show'])->whereNumber('property');

<?php

use App\Http\Controllers\Api\LegalTextController;
use App\Http\Controllers\Api\LegalTextVersionController;
use App\Http\Controllers\MockShopController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:60,1'])->name('api.')->group(function () {
    Route::get('legal-texts', [LegalTextController::class, 'index'])->name('legal-texts.index');
    Route::get('legal-texts/{type}', [LegalTextController::class, 'show'])->name('legal-texts.show');
    Route::get('legal-texts/{type}/versions', [LegalTextVersionController::class, 'index'])->name('legal-texts.versions.index');
});

Route::post('mock-shop/{shop}', MockShopController::class)
    ->middleware('throttle:120,1')
    ->name('mock-shop');

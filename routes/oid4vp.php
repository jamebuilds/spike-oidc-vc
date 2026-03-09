<?php

use App\Http\Controllers\Oid4vp\GetPresentationStatusController;
use App\Http\Controllers\Oid4vp\PresentationRequestController;
use App\Http\Controllers\Oid4vp\StorePresentationResponseController;
use Illuminate\Support\Facades\Route;

Route::prefix('oid4vp')->group(function () {
    Route::get('/', [PresentationRequestController::class, 'create'])->name('oid4vp.create');
    Route::post('/', [PresentationRequestController::class, 'store'])->name('oid4vp.store');
    Route::get('/{id}', [PresentationRequestController::class, 'show'])->name('oid4vp.show');

    Route::post('/{id}/response', StorePresentationResponseController::class)->name('oid4vp.response');
    Route::get('/{id}/status', GetPresentationStatusController::class)->name('oid4vp.status');
});

<?php

use App\Http\Controllers\GetPresentationStatus;
use App\Http\Controllers\PresentationRequestController;
use App\Http\Controllers\StorePresentationResponse;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('oid4vp')->group(function () {
    Route::get('/', [PresentationRequestController::class, 'create'])->name('oid4vp.create');
    Route::post('/', [PresentationRequestController::class, 'store'])->name('oid4vp.store');
    Route::get('/{id}', [PresentationRequestController::class, 'show'])->name('oid4vp.show');

    Route::post('/{id}/response', StorePresentationResponse::class)->name('oid4vp.response');
    Route::get('/{id}/status', GetPresentationStatus::class)->name('oid4vp.status');
});

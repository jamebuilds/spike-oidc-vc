<?php

use App\Http\Controllers\Oid4vp\GetPresentationDefinitionController;
use App\Http\Controllers\Oid4vp\GetPresentationStatusController;
use App\Http\Controllers\Oid4vp\PresentationRequestController;
use App\Http\Controllers\Oid4vp\StorePresentationResponseController;
use Illuminate\Support\Facades\Route;

Route::resource('oid4vp', PresentationRequestController::class)
    ->only(['create', 'store', 'show'])
    ->parameters(['oid4vp' => 'id']);

Route::get('/oid4vp/{id}/pd', GetPresentationDefinitionController::class)->name('oid4vp.pd');
Route::post('/oid4vp/{id}/response', StorePresentationResponseController::class)->name('oid4vp.response');
Route::get('/oid4vp/{id}/status', GetPresentationStatusController::class)->name('oid4vp.status');

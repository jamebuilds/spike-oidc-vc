<?php

use App\Http\Controllers\DcApi\DigitalCredentialController;
use App\Http\Controllers\DcApi\VerifyDigitalCredentialController;
use Illuminate\Support\Facades\Route;

Route::get('/dc-api/create', [DigitalCredentialController::class, 'create'])->name('dc-api.create');
Route::post('/dc-api', [DigitalCredentialController::class, 'store'])->name('dc-api.store');
Route::post('/dc-api/{id}/verify', VerifyDigitalCredentialController::class)->name('dc-api.verify');

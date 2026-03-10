<?php

use App\Http\Controllers\Wallet\AuthorizationController;
use App\Http\Controllers\Wallet\CredentialController;
use App\Http\Controllers\Wallet\PresentationLogController;
use App\Http\Controllers\Wallet\WalletDashboardController;
use App\Http\Middleware\AutoLogin;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(AutoLogin::class)->prefix('wallet')->group(function () {
    Route::get('/', WalletDashboardController::class)->name('wallet.dashboard');

    Route::get('/credentials/{credential}', [CredentialController::class, 'show'])->name('wallet.credentials.show');
    Route::delete('/credentials/{credential}', [CredentialController::class, 'destroy'])->name('wallet.credentials.destroy');

    Route::get('/authorizations/create', [AuthorizationController::class, 'create'])->name('wallet.authorizations.create');
    Route::post('/authorizations', [AuthorizationController::class, 'store'])->name('wallet.authorizations.store');

    Route::get('/presentation-logs', [PresentationLogController::class, 'index'])->name('wallet.presentation-logs.index');
});

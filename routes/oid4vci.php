<?php

use App\Http\Controllers\Oid4vci\CredentialController;
use App\Http\Controllers\Oid4vci\CredentialOfferController;
use App\Http\Controllers\Oid4vci\CredentialTypeMetadataController;
use App\Http\Controllers\Oid4vci\GetCredentialOfferController;
use App\Http\Controllers\Oid4vci\GetIssuanceStatusController;
use App\Http\Controllers\Oid4vci\IssuerMetadataController;
use App\Http\Controllers\Oid4vci\TokenController;
use Illuminate\Support\Facades\Route;

Route::resource('oid4vci', CredentialOfferController::class)
    ->only(['create', 'store'])
    ->parameters(['oid4vci' => 'id']);

Route::get('/oid4vci/{id}/offer', GetCredentialOfferController::class)->name('oid4vci.offer');
Route::post('/oid4vci/token', TokenController::class)->name('oid4vci.token');
Route::post('/oid4vci/credential', CredentialController::class)->name('oid4vci.credential');
Route::get('/oid4vci/{id}/status', GetIssuanceStatusController::class)->name('oid4vci.status');

Route::get('/.well-known/openid-credential-issuer', IssuerMetadataController::class)
    ->name('oid4vci.metadata');

Route::get('/AccredifyEmployeePass', CredentialTypeMetadataController::class)
    ->name('oid4vci.type-metadata');

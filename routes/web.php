<?php

use App\Http\Controllers\IdCardController;
use App\Http\Controllers\IdCardDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/id-cards/{idCard}/download', IdCardDownloadController::class)
    ->name('id-cards.download')
    ->middleware('auth');

Route::get('/id-card-print-batches/{record}/download', \App\Http\Controllers\IdCardPrintBatchDownloadController::class)
    ->name('id-card-print-batches.download')
    ->middleware('auth');

Route::get('/verify-id-card/{card}', [IdCardController::class, 'verify'])
    ->name('id-cards.verify')
    ->middleware('signed');

<?php

use Illuminate\Support\Facades\Route;
use Modules\StockTransfer\Http\Controllers\StockTransferController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('stock_transfers')->controller(StockTransferController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('delete/{id}', 'destroy');
    });
});

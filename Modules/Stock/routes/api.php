<?php

use Illuminate\Support\Facades\Route;
use Modules\Stock\Http\Controllers\StockController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('stock')->controller(StockController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('summary', 'summary');
        Route::get('summary/export', 'summaryExport');
        Route::middleware('role:warehouse staff')->post('/', 'store');
        Route::middleware('role:warehouse staff')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
    });
});
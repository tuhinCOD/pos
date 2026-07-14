<?php

use Illuminate\Support\Facades\Route;
use Modules\Temp\Http\Controllers\TempController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('temps')->controller(TempController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('/grouped', 'grouped');
        Route::get('/by-invoice/{invoice_no}', 'byInvoice');
        Route::get('/today', 'today');
        Route::get('/{id}', 'show');
        Route::middleware('role:cashier,admin,manager')->post('/', 'store');
        Route::middleware('role:cashier,admin,manager')->post('update/{id}', 'update');
        Route::middleware('role:cashier,admin,manager')->post('batch-status', 'batchStatus');
        Route::middleware('role:cashier,admin,manager')->post('validate-batch', 'validateBatch');
        Route::middleware('role:cashier,admin,manager')->post('delete/{id}', 'destroy');
        Route::middleware('role:cashier,admin,manager')->post('delete-by-invoice', 'destroyByInvoice');
        Route::middleware('role:cashier,admin,manager')->post('delete-bulk', 'destroyBulk');
    });
});

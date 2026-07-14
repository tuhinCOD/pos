<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('purchases')->controller(PurchaseController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('today', 'today');
        Route::get('by-invoice/{invoiceNo}', 'byInvoice');
        Route::get('{id}', 'show');
        Route::middleware('role:admin,warehouse staff,manager')->post('/', 'store');
        Route::middleware('role:admin,warehouse staff,manager')->post('update/{id}', 'update');
        Route::middleware('role:admin,warehouse staff,manager')->post('delete-by-invoice', 'destroyByInvoice');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Sale\Http\Controllers\SaleController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('sales')->controller(SaleController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('by-invoice/{invoice_no}', 'byInvoice');
        Route::get('{id}', 'show')->whereNumber('id');
        Route::middleware('role:admin,manager,cashier')->post('/', 'store');
        Route::middleware('role:admin,manager')->post('update/{id}', 'update');
        Route::middleware('role:admin,manager,cashier')->post('product/{id}', 'getProductPriceByProduct');
        Route::middleware('role:admin,manager,cashier')->post('validate-batch', 'validateBatch');
        Route::middleware('role:admin')->post('delete-by-invoice', 'destroyByInvoice');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
    });
});

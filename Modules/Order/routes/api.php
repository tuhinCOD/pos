<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\OrderController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('orders')->controller(OrderController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('by-invoice/{invoice_no}', 'byInvoice');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('product/{id}', 'getProductPriceByProduct');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});
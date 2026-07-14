<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductPrice\Http\Controllers\ProductPriceController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('product_prices')->controller(ProductPriceController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('latest/{productId}', 'latest');
        Route::middleware('role:admin,manager')->post('/', 'store');
        Route::middleware('role:admin,manager')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});

Route::middleware('auth:api')->prefix('product-prices')->controller(ProductPriceController::class)->group(function () {
    Route::get('latest/{productId}', 'latest');
});
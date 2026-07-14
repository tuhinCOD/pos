<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductDiscount\Http\Controllers\ProductDiscountController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('product_discounts')->controller(ProductDiscountController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('{id}', 'show');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('delete/{id}', 'destroy');
        Route::post('product/{id}', 'getProductPriceByProduct');
    });
});
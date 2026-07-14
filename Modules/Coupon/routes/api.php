<?php

use Illuminate\Support\Facades\Route;
use Modules\Coupon\Http\Controllers\CouponController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('coupons')->controller(CouponController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('delete/{id}', 'destroy');
    });
});

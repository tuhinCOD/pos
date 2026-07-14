<?php

use Illuminate\Support\Facades\Route;
use Modules\Delivery\Http\Controllers\DeliveryController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('deliveries')->controller(DeliveryController::class)->group(function () {
        Route::get('/', 'index');
        Route::middleware('role:admin')->post('/', 'store');
        Route::middleware('role:admin')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\CartController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('cart')->controller(CartController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('delete/{id}', 'destroy');
    });
});
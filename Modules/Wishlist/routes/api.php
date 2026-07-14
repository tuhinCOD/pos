<?php

use Illuminate\Support\Facades\Route;
use Modules\Wishlist\Http\Controllers\WishlistController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('wishlists')->controller(WishlistController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('delete/{id}', 'destroy');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductReview\Http\Controllers\ProductReviewController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('product_reviews')->controller(ProductReviewController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('update/{id}', 'update');
        Route::post('delete/{id}', 'destroy');
        Route::post('image/delete/{id}', 'destroyImage');
    });

});

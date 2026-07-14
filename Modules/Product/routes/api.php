<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('products')->controller(ProductController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::middleware(['role:admin,manager'])->post('/', 'store');
        Route::middleware(['role:admin,manager'])->post('update/{id}', 'update');
        Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
        Route::middleware(['role:admin'])->post('delete-bulk', 'destroyBulk');
        Route::middleware(['role:admin,manager'])->post('image/delete/{id}', 'destroyImage');
    });
});

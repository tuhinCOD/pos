<?php

use Illuminate\Support\Facades\Route;
use Modules\Category\Http\Controllers\CategoryController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('categories')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::middleware(['role:admin,manager'])->post('/', 'store');
        Route::middleware(['role:admin,manager'])->post('update/{id}', 'update');
        Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
        Route::middleware(['role:admin'])->post('delete-bulk', 'destroyBulk');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\SupplierReturn\Http\Controllers\SupplierReturnController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('supplier_returns')->controller(SupplierReturnController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('{id}', 'show');
        Route::middleware('role:admin,manager')->post('/', 'store');
        Route::middleware('role:admin,manager')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});
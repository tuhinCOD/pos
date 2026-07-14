<?php

use Illuminate\Support\Facades\Route;
use Modules\Repair\Http\Controllers\RepairController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('repairs')->controller(RepairController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('/{id}', 'show');
        // Route::post('/', 'store');
        Route::middleware('role:admin,manager')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});

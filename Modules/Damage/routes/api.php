<?php

use Illuminate\Support\Facades\Route;
use Modules\Damage\Http\Controllers\DamageController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('damages')->controller(DamageController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('/{id}', 'show');
        Route::middleware('role:admin,manager,warehouse staff')->post('/', 'store');
        Route::middleware('role:admin,manager,warehouse staff')->post('update/{id}', 'update');
        Route::middleware('role:admin')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin')->post('delete-bulk', 'destroyBulk');
    });
});

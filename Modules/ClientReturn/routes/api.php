<?php

use Illuminate\Support\Facades\Route;
use Modules\ClientReturn\Http\Controllers\ClientReturnController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('client_returns')->controller(ClientReturnController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('/{id}', 'show');
        Route::middleware('role:admin,manager,cashier')->post('/', 'store');
        Route::middleware('role:admin,manager,cashier')->post('update/{id}', 'update');
        Route::middleware('role:admin,manager')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin,manager')->post('delete-bulk', 'destroyBulk');
    });
});
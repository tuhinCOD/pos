<?php

use Illuminate\Support\Facades\Route;
use Modules\Credit\Http\Controllers\CreditController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('credits')->controller(CreditController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::get('{id}', 'show');

        Route::middleware('role:admin,manager,cashier')->post('update/{id}', 'update');
        Route::middleware('role:admin,manager')->post('delete/{id}', 'destroy');
        Route::middleware('role:admin,manager')->post('delete-bulk', 'destroyBulk');
    });
});

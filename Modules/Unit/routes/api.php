<?php

use Illuminate\Support\Facades\Route;
use Modules\Unit\Http\Controllers\UnitController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('units')->controller(UnitController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::middleware(['role:admin,manager'])->post('/', 'store');
        Route::middleware(['role:admin,manager'])->post('update/{id}', 'update');
        Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
        Route::middleware(['role:admin'])->post('delete-bulk', 'destroyBulk');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Branch\Http\Controllers\BranchController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('branches')->controller(BranchController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('export', 'export');
        Route::get('download/{filename}', 'download');
        Route::middleware(['role:admin'])->post('/', 'store');
        Route::middleware(['role:admin'])->post('update/{id}', 'update');
        Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
        Route::middleware(['role:admin'])->post('delete-bulk', 'destroyBulk');
    });
});
<?php

use Illuminate\Support\Facades\Route;
use Modules\Company\Http\Controllers\CompanyController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('companies')->controller(CompanyController::class)->group(function () {
        Route::get('/', 'index');
        Route::middleware(['role:admin'])->post('/', 'store');
        Route::middleware(['role:admin'])->post('update/{id}', 'update');
        Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
    });
});
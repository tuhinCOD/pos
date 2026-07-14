<?php

use Illuminate\Support\Facades\Route;
use Modules\Unit\Http\Controllers\UnitController;

Route::middleware(['auth', 'verified'])->prefix('units')->controller(UnitController::class)->group(function () {
    Route::get('/', 'index')->name('unit.index');
    Route::post('/', 'store')->name('unit.store');
    Route::post('update/{id}', 'update')->name('unit.update');
    Route::post('delete/{id}', 'destroy')->name('unit.destroy');
});

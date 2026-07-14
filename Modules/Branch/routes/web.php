<?php

use Illuminate\Support\Facades\Route;
use Modules\Branch\Http\Controllers\BranchController;

Route::middleware(['auth', 'verified'])->prefix('branches')->controller(BranchController::class)->group(function () {
    Route::get('/', 'index')->name('branch.index');
    Route::post('/', 'store')->name('branch.store');
    Route::post('update/{id}', 'update')->name('branch.update');
    Route::post('delete/{id}', 'destroy')->name('branch.destroy');
});

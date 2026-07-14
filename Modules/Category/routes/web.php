<?php

use Illuminate\Support\Facades\Route;
use Modules\Category\Http\Controllers\CategoryController;

Route::middleware(['auth', 'verified'])->prefix('categories')->controller(CategoryController::class)->group(function () {
    Route::get('/', 'index')->name('category.index');
    Route::post('/', 'store')->name('category.store');
    Route::post('update/{id}', 'update')->name('category.update');
    Route::post('delete/{id}', 'destroy')->name('category.destroy');
});

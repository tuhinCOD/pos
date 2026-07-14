<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductImage\Http\Controllers\ProductImageController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productimages', ProductImageController::class)->names('productimage');
});

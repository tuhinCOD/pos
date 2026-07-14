<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductImage\Http\Controllers\ProductImageController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('productimages', ProductImageController::class)->names('productimage');
});

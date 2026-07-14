<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductReviewImage\Http\Controllers\ProductReviewImageController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('productreviewimages', ProductReviewImageController::class)->names('productreviewimage');
});

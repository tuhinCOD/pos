<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductReviewImage\Http\Controllers\ProductReviewImageController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productreviewimages', ProductReviewImageController::class)->names('productreviewimage');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductReview\Http\Controllers\ProductReviewController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productreviews', ProductReviewController::class)->names('productreview');
});

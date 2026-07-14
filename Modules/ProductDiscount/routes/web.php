<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductDiscount\Http\Controllers\ProductDiscountController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productdiscounts', ProductDiscountController::class)->names('productdiscount');
});

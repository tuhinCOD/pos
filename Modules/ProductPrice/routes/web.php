<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductPrice\Http\Controllers\ProductPriceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('productprices', ProductPriceController::class)->names('productprice');
});

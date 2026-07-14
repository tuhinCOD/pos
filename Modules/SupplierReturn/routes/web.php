<?php

use Illuminate\Support\Facades\Route;
use Modules\SupplierReturn\Http\Controllers\SupplierReturnController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('supplierreturns', SupplierReturnController::class)->names('supplierreturn');
});

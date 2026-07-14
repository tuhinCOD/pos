<?php

use Illuminate\Support\Facades\Route;
use Modules\Barcode\Http\Controllers\BarcodeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('barcodes', BarcodeController::class)->names('barcode');
});

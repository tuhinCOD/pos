<?php

use Illuminate\Support\Facades\Route;
use Modules\Barcode\Http\Controllers\BarcodeController;

Route::middleware('auth:api')->group(function () {
    Route::get('barcodes', [BarcodeController::class, 'index'])->name('barcode.index');
    Route::get('barcodes/export', [BarcodeController::class, 'export'])->name('barcode.export');
    Route::get('barcodes/download/{filename}', [BarcodeController::class, 'download'])->name('barcode.download');
    Route::get('barcodes/{id}', [BarcodeController::class, 'show'])->name('barcode.show');
    Route::get('barcodes/by-purchase/{purchaseId}', [BarcodeController::class, 'byPurchase'])->name('barcode.byPurchase');
    Route::get('barcodes/by-product/{productId}', [BarcodeController::class, 'byProduct'])->name('barcode.byProduct');
    Route::middleware('role:admin,warehouse staff,manager')->post('barcodes/generate', [BarcodeController::class, 'generate'])->name('barcode.generate');
    Route::middleware('role:admin,warehouse staff,manager,cashier')->post('barcodes/generate-single', [BarcodeController::class, 'generateSingle'])->name('barcode.generateSingle');
    Route::middleware('role:admin,warehouse staff,manager,cashier')->post('barcodes/update/{id}', [BarcodeController::class, 'update'])->name('barcode.update');
    Route::middleware('role:admin')->post('barcodes/delete/{id}', [BarcodeController::class, 'destroy'])->name('barcode.destroy');
    Route::middleware('role:admin')->post('barcodes/delete-bulk', [BarcodeController::class, 'destroyBulk'])->name('barcode.destroyBulk');
});

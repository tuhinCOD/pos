<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::get('payments/total/{invoice_no}', [PaymentController::class, 'totalByInvoice']);
    Route::get('payments', [PaymentController::class, 'index'])->name('payment.index');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payment.show');
    Route::middleware('role:admin')->post('payments', [PaymentController::class, 'store'])->name('payment.store');
    Route::middleware('role:admin')->put('payments/{payment}', [PaymentController::class, 'update'])->name('payment.update');
    Route::middleware('role:admin')->delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payment.destroy');
});

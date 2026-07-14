<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentMethod\Http\Controllers\PaymentMethodController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('paymentmethods', PaymentMethodController::class)->names('paymentmethod');
});

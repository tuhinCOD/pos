<?php

use Illuminate\Support\Facades\Route;
use Modules\Credit\Http\Controllers\CreditController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('credits', CreditController::class)->names('credit');
});

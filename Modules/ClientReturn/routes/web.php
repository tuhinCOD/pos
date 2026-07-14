<?php

use Illuminate\Support\Facades\Route;
use Modules\ClientReturn\Http\Controllers\ClientReturnController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('clientreturns', ClientReturnController::class)->names('clientreturn');
});

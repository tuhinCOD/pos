<?php

use Illuminate\Support\Facades\Route;
use Modules\Damage\Http\Controllers\DamageController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('damages', DamageController::class)->names('damage');
});

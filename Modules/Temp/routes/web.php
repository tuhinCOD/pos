<?php

use Illuminate\Support\Facades\Route;
use Modules\Temp\Http\Controllers\TempController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('temps', TempController::class)->names('temp');
});

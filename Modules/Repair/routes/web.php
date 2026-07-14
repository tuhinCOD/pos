<?php

use Illuminate\Support\Facades\Route;
use Modules\Repair\Http\Controllers\RepairController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('repairs', RepairController::class)->names('repair');
});

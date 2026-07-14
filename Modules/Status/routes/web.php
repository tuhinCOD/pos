<?php

use Illuminate\Support\Facades\Route;
use Modules\Status\Http\Controllers\StatusController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('statuses', StatusController::class)->names('status');
});

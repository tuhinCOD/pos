<?php

use Illuminate\Support\Facades\Route;
use Modules\Status\Http\Controllers\StatusController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('statuses', StatusController::class)->names('status');
});

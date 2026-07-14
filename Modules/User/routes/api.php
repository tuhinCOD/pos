<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::middleware('auth:api')->group(function () {
        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('export', 'export');
            Route::get('download/{filename}', 'download');
            Route::middleware(['role:admin,super admin,manager,cashier', 'can_create_user_role'])->post('/', 'store');
            Route::middleware(['role:admin,super admin,manager,cashier', 'can_create_user_role'])->post('update/{id}', 'update');
            Route::middleware(['role:admin'])->post('delete/{id}', 'destroy');
            Route::middleware(['role:admin'])->post('delete-bulk', 'destroyBulk');
        });
});

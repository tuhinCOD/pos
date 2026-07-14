<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('auth:api')->group(function () {
    Route::post('/password-change', [AuthController::class, 'changePassword'])->name('password.change');
    Route::get('/export-check/{filename}', function ($filename) {
        return response()->json([
            'ready' => Storage::disk('public')->exists('exports/' . $filename)
        ]);
    });
});

Route::post('/verification', [AuthController::class, 'verifyOtp']);

Route::post('/forget-pass', [AuthController::class, 'sendResetLink'])->name('password.email');

Route::post('/reset-pass', [AuthController::class, 'resetPassword'])->name('password.update');

Route::group([
    'middleware' => 'api'
], function ($router) {
    Route::post('/signup', [AuthController::class, 'sendOtp']);
    Route::post('/login', [AuthController::class, 'authenticate']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
});

Route::prefix('storefront')->group(function () {
    Route::get('/company', [App\Http\Controllers\StorefrontController::class, 'companyInfo']);
    Route::get('/featured-products', [App\Http\Controllers\StorefrontController::class, 'featuredProducts']);
    Route::get('/products', [App\Http\Controllers\StorefrontController::class, 'products']);
    Route::get('/products/{id}', [App\Http\Controllers\StorefrontController::class, 'productDetail']);
    Route::get('/categories', [App\Http\Controllers\StorefrontController::class, 'categories']);
    Route::get('/products/{productId}/reviews', [App\Http\Controllers\StorefrontController::class, 'productReviews']);
    Route::post('/validate-coupon', [App\Http\Controllers\StorefrontController::class, 'validateCoupon'])->middleware('auth:api');
    Route::post('/place-order', [App\Http\Controllers\StorefrontController::class, 'placeOrder'])->middleware('auth:api');
    Route::get('/my-orders', [App\Http\Controllers\StorefrontController::class, 'myOrders'])->middleware('auth:api');
    Route::post('/update-profile', [App\Http\Controllers\StorefrontController::class, 'updateProfile'])->middleware('auth:api');
    Route::get('/cities', [App\Http\Controllers\StorefrontController::class, 'cities']);
});
<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SellerController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SetLanguage;
use App\Http\Middleware\EnsureApiTokenIsValid;



Route::middleware([SetLanguage::class])->group(function () {


    Route::post('/login', [RegisterController::class, 'login']);
    Route::post('customer/register', [RegisterController::class, 'customerRegister']);
    Route::post('seller/register', [RegisterController::class, 'sellerRegister']);
    Route::post('guest/register', [RegisterController::class, 'guestRegister']);

    Route::post('register/social/{provider}', [RegisterController::class, 'socialRegister']);

    Route::post('password/forgot', [RegisterController::class, 'sendResetOtp']);
    Route::post('password/verify-otp', [RegisterController::class, 'verifyOtp']);
    Route::post('password/reset', [RegisterController::class, 'resetPassword']);

    Route::middleware([EnsureApiTokenIsValid::class, 'auth:api'])->group(function () {

        Route::post('update-password', [RegisterController::class, 'updatePassword']);

        Route::get('categories', [CategoryController::class, 'index']);

        Route::get('ads', [AdsController::class, 'index']);



        Route::middleware(['role:admin|seller'])->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

            Route::post('ads', [AdsController::class, 'store']);
        });

        // Admin routes
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            Route::post('/{seller}/status', [SellerController::class, 'updateSellerStatus']);
        });

        // Seller routes
        Route::middleware(['role:seller'])->prefix('seller')->group(function () {});

        // Customer routes
        Route::middleware(['role:customer'])->prefix('customer')->group(function () {
            Route::post('upgrade-to-seller', [RegisterController::class, 'upgradeToSeller']);
        });
    });
});

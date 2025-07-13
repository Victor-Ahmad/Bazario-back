<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\TalentController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SetLanguage;
use App\Http\Middleware\EnsureApiTokenIsValid;



Route::middleware([SetLanguage::class])->group(function () {


    Route::post('/login', [RegisterController::class, 'login']);
    Route::post('customer/register', [RegisterController::class, 'customerRegister']);
    Route::post('seller/register', [RegisterController::class, 'sellerRegister']);
    Route::post('talent/register', [RegisterController::class, 'talentRegister']);

    Route::post('guest/register', [RegisterController::class, 'guestRegister']);

    Route::post('register/social/{provider}', [RegisterController::class, 'socialRegister']);

    Route::post('password/forgot', [RegisterController::class, 'sendResetOtp']);
    Route::post('password/verify-otp', [RegisterController::class, 'verifyOtp']);
    Route::post('password/reset', [RegisterController::class, 'resetPassword']);

    Route::middleware([EnsureApiTokenIsValid::class, 'auth:api'])->group(function () {

        Route::post('update-password', [RegisterController::class, 'updatePassword']);

        Route::get('categories', [CategoryController::class, 'index']);

        Route::get('ads', [AdsController::class, 'index']);


        Route::get('/sellers', [SellerController::class, 'index']);
        Route::get('/talents', [TalentController::class, 'index']);
        Route::get('/customers', [CustomerController::class, 'index']);



        Route::middleware(['role:admin|seller'])->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('ads', [AdsController::class, 'store']);
            Route::post('product', [ProductController::class, 'store']);
            Route::get('products', [ProductController::class, 'index']);
            Route::get('my-products', [ProductController::class, 'myProducts']);
            Route::get('products/{id}', [ProductController::class, 'productsBySeller']);
        });

        // Admin routes
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            Route::post('seller/{seller}/status', [SellerController::class, 'updateSellerStatus']);
            Route::post('talent/{talent}/status', [TalentController::class, 'updateTalentStatus']);
        });

        Route::middleware(['role:admin'])->prefix('seller')->group(function () {
            Route::get('/requests', [SellerController::class, 'requests']);
        });


        // Seller routes
        Route::middleware(['role:seller'])->prefix('seller')->group(function () {});

        // Customer routes
        Route::middleware(['role:customer'])->prefix('customer')->group(function () {
            Route::post('upgrade-to-seller', [RegisterController::class, 'upgradeToSeller']);
        });
    });
});

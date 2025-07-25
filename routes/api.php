<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ServiceController;
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


    Route::get('sellers', [SellerController::class, 'index']);
    Route::get('talents', [TalentController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('ads', [AdController::class, 'index']);
    Route::get('ads/timed', [AdController::class, 'timedAds']);

    Route::get('talent/{id}/services', [ServiceController::class, 'servicesByTalent']);
    Route::get('seller/{id}/products', [ProductController::class, 'productsBySeller']);



    Route::middleware([EnsureApiTokenIsValid::class, 'auth:api'])->group(function () {

        Route::post('update-password', [RegisterController::class, 'updatePassword']);

        Route::get('pending-ads', [AdController::class, 'getPendingAds']);
        Route::post('category', [CategoryController::class, 'store']);
        Route::get('category/{category}/products', [ProductController::class, 'productsByCategory']);






        Route::get('/customers', [CustomerController::class, 'index']);


        Route::get('products/{id}', [ProductController::class, 'productsBySeller']);

        Route::middleware(['role:admin|seller|talent'])->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('ads', [AdsController::class, 'store']);
            Route::post('product', [ProductController::class, 'store']);
            Route::post('service', [ServiceController::class, 'store']);
            Route::get('my-products', [ProductController::class, 'myProducts']);
            Route::get('my-services', [ServiceController::class, 'myServices']);
            Route::post('ads/{ad}/images', [AdController::class, 'addImages']);
            Route::post('ads', [AdController::class, 'store']);
        });





        Route::middleware(['role:admin'])->group(function () {
            Route::prefix('admin')->group(function () {
                Route::post('seller/{seller}/status', [SellerController::class, 'updateSellerStatus']);
                Route::post('talent/{talent}/status', [TalentController::class, 'updateTalentStatus']);
            });

            Route::prefix('seller')->group(function () {
                Route::get('/requests', [SellerController::class, 'requests']);
            });
            Route::prefix('talent')->group(function () {
                Route::get('/requests', [TalentController::class, 'requests']);
            });



            Route::prefix('ads')->group(function () {
                Route::post('/{ad}/status', [AdController::class, 'updateStatus']);
                Route::prefix('/requests')->group(function () {
                    Route::get('timed', [AdController::class, 'timedAdRequests']);
                    Route::get('banner', [AdController::class, 'bannerdAdRequests']);
                });
            });
        });


        // Seller routes
        Route::middleware(['role:seller'])->prefix('seller')->group(function () {});

        // Customer routes
        Route::middleware(['role:customer'])->prefix('customer')->group(function () {
            Route::post('upgrade-to-seller', [RegisterController::class, 'upgradeToSeller']);
            Route::post('upgrade-to-talent', [RegisterController::class, 'upgradeToTalent']);
        });
    });
});

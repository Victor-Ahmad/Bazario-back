<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AdsController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ListingAdController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceProviderController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SetLanguage;
use App\Http\Middleware\EnsureApiTokenIsValid;
use Illuminate\Support\Facades\Broadcast;


Broadcast::routes([
    'middleware' => ['auth:api'],
]);

Route::middleware([SetLanguage::class])->group(function () {


    Route::post('/login', [RegisterController::class, 'login']);
    Route::post('customer/register', [RegisterController::class, 'register']);

    // Route::post('register/social/{provider}', [RegisterController::class, 'socialRegister']);

    Route::post('password/forgot', [RegisterController::class, 'sendResetOtp']);
    Route::post('password/verify-otp', [RegisterController::class, 'verifyOtp']);
    Route::post('password/reset', [RegisterController::class, 'resetPassword']);


    Route::get('sellers', [SellerController::class, 'index']);
    Route::get('service_providers', [ServiceProviderController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('services', [ServiceController::class, 'index']);
    Route::get('ads/timed', [AdController::class, 'timedAds']);

    Route::get('service_provider/{id}/services', [ServiceController::class, 'servicesByServiceProvider']);
    Route::get('seller/{id}/products', [ProductController::class, 'productsBySeller']);

    // Route::post('seller/register', [RegisterController::class, 'sellerRegister']);
    // Route::post('service_provider/register', [RegisterController::class, 'service_providerRegister']);
    // Route::post('guest/register', [RegisterController::class, 'guestRegister']);

    Route::get('ads', [AdController::class, 'index']);
    Route::get('ads/gold', [AdController::class, 'goldIndex']);
    Route::get('ads/silver', [AdController::class, 'silverIndex']);
    Route::get('ads/normal', [AdController::class, 'normalIndex']);

    Route::middleware([EnsureApiTokenIsValid::class, 'auth:api'])->group(function () {

        Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);

        Route::get('/conversations', [ConversationController::class, 'index']);

        // Start or get a 1-1 conversation
        Route::post('/conversations/direct', [ConversationController::class, 'startDirect']);

        // Get messages for a conversation (paginated)
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);

        // Send message
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

        // Acknowledge delivery (recipient device confirms it received via WS)
        Route::post('/messages/{message}/delivered', [MessageController::class, 'ackDelivered']);

        // Mark read (recipient viewed)
        Route::post('/messages/{message}/read', [MessageController::class, 'markRead']);

        // (Optional) mark entire conversation read up to latest
        Route::post('/conversations/{conversation}/read', [MessageController::class, 'markConversationRead']);



        Route::post('update-password', [RegisterController::class, 'updatePassword']);



        Route::post('category', [CategoryController::class, 'store']);
        Route::get('category/{category}/products', [ProductController::class, 'productsByCategory']);


        Route::post('ads/{ad}/images', [AdController::class, 'addImages']);
        Route::post('ads', [AdController::class, 'store']);
        Route::post('/listing-ads', [ListingAdController::class, 'store']);


        Route::get('/customers', [CustomerController::class, 'index']);


        Route::get('products/{id}', [ProductController::class, 'productsBySeller']);

        Route::middleware(['role:admin|seller|service_provider'])->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('product', [ProductController::class, 'store']);
            Route::post('service', [ServiceController::class, 'store']);
            Route::get('my-products', [ProductController::class, 'myProducts']);
            Route::get('my-services', [ServiceController::class, 'myServices']);
        });





        Route::middleware(['role:admin'])->group(function () {
            Route::prefix('admin')->group(function () {
                Route::post('seller/{seller}/status', [SellerController::class, 'updateSellerStatus']);
                Route::post('service_provider/{service_provider}/status', [ServiceProviderController::class, 'updateServiceProviderStatus']);
                Route::post('ad/{ad}/status', [AdController::class, 'updateStatus']);
            });

            Route::prefix('seller')->group(function () {
                Route::get('/requests', [SellerController::class, 'requests']);
            });
            Route::prefix('service_provider')->group(function () {
                Route::get('/requests', [ServiceProviderController::class, 'requests']);
            });


            Route::prefix('ads')->group(function () {
                Route::get('pending', [AdController::class, 'getPendingAds']);
                Route::get('general', [AdController::class, 'getGeneralAds']);
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
            Route::post('upgrade-to-service_provider', [RegisterController::class, 'upgradeToServiceProvider']);
        });
    });
});

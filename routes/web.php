<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('demo')->middleware(['throttle:auth'])->group(function () {
    Route::get('auth/csrf', [LoginController::class, 'demoCsrf']);
    Route::post('login', [LoginController::class, 'demoLogin']);
});
Route::middleware(['throttle:auth'])->group(function () {
    Route::get('auth/csrf', [LoginController::class, 'demoCsrf']);
});

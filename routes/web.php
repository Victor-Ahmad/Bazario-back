<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('demo/auth')->middleware(['throttle:auth'])->group(function () {
    Route::get('csrf', [LoginController::class, 'demoCsrf']);
    Route::post('admin/login', [LoginController::class, 'demoAdminLogin']);
});
Route::middleware(['throttle:auth'])->group(function () {
    Route::get('auth/csrf', [LoginController::class, 'demoCsrf']);
    Route::post('auth/admin/login', [LoginController::class, 'demoAdminLogin']);
});

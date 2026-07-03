<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\LogoutController;
use App\Modules\Auth\Http\Controllers\RegisterController;
use App\Modules\Auth\Http\Controllers\VkAuthController;
use App\Modules\Auth\Http\Controllers\YandexAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');

    Route::get('/auth/yandex/redirect', [YandexAuthController::class, 'redirect'])
        ->name('auth.yandex.redirect');

    Route::get('/auth/yandex/callback', [YandexAuthController::class, 'callback'])
        ->name('auth.yandex.callback');

    Route::get('/auth/vk/redirect', [VkAuthController::class, 'redirect'])
        ->name('auth.vk.redirect');

    Route::get('/auth/vk/callback', [VkAuthController::class, 'callback'])
        ->name('auth.vk.callback');

    Route::get('/register', [RegisterController::class, 'create'])
        ->name('register');

    Route::post('/register', [RegisterController::class, 'store'])
        ->name('register.store');

});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

<?php

use App\Modules\Identity\Presentation\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/settings', [UserSettingsController::class, 'index'])
        ->name('settings.index');

    Route::patch('/settings/profile', [UserSettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    Route::patch('/settings/telegram', [UserSettingsController::class, 'updateTelegram'])
        ->name('settings.telegram.update');

    Route::post('/settings/telegram/confirm', [UserSettingsController::class, 'confirmTelegram'])
        ->name('settings.telegram.confirm');

    Route::patch('/settings/max', [UserSettingsController::class, 'updateMax'])
        ->name('settings.max.update');

    Route::post('/settings/max/confirm', [UserSettingsController::class, 'confirmMax'])
        ->name('settings.max.confirm');
});


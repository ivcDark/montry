<?php

use App\Modules\Notifications\Presentation\Http\Controllers\MaxWebhookController;
use App\Modules\Notifications\Presentation\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::post('/max/webhook', MaxWebhookController::class)
    ->name('max.webhook');


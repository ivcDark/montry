<?php

use App\Modules\Feedback\Presentation\Http\Controllers\FeedbackMessageController;
use App\Modules\Feedback\Presentation\Http\Controllers\ProductIdeaController;
use Illuminate\Support\Facades\Route;

Route::post('/feedback', [FeedbackMessageController::class, 'store'])
    ->name('feedback.store');

Route::post('/product-ideas', [ProductIdeaController::class, 'store'])
    ->middleware('auth')
    ->name('product-ideas.store');

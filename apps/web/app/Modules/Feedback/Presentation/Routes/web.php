<?php

use App\Modules\Feedback\Presentation\Http\Controllers\FeedbackMessageController;
use Illuminate\Support\Facades\Route;

Route::post('/feedback', [FeedbackMessageController::class, 'store'])
    ->name('feedback.store');

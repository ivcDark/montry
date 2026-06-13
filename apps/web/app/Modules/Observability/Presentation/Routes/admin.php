<?php

use App\Modules\Observability\Presentation\Http\Controllers\DeadLetterIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/dead-letters', DeadLetterIndexController::class)
    ->name('dead-letters.index');


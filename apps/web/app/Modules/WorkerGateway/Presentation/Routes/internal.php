<?php

use App\Modules\WorkerGateway\Presentation\Http\Controllers\InternalCheckResultController;
use Illuminate\Support\Facades\Route;

Route::post('/check-results', [InternalCheckResultController::class, 'store'])
    ->name('internal.check-results.store');

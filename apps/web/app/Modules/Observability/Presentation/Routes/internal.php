<?php

use App\Modules\Observability\Presentation\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', MetricsController::class)
    ->name('internal.metrics');

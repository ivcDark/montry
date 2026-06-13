<?php

use App\Modules\WorkerGateway\Presentation\Http\Controllers\DueMonitorController;
use App\Modules\WorkerGateway\Presentation\Http\Controllers\InternalCheckResultController;
use Illuminate\Support\Facades\Route;

Route::get('/monitors/due', [DueMonitorController::class, 'index'])
    ->name('internal.monitors.due');

Route::post('/check-results', [InternalCheckResultController::class, 'store'])
    ->name('internal.check-results.store');

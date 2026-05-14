<?php

use App\Modules\Monitoring\Presentation\Http\Controllers\MonitorController;
use App\Modules\Monitoring\Presentation\Http\Controllers\MonitorIndexController;
use App\Modules\WorkerGateway\Presentation\Http\Controllers\ManualCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/monitors', MonitorIndexController::class)
        ->name('monitors.index');

    Route::post('/monitors/{monitor}/check-now', [ManualCheckController::class, 'store'])
        ->name('monitors.check-now');

    Route::get('/sites/{site}/monitors/create', [MonitorController::class, 'create'])
        ->name('sites.monitors.create');

    Route::post('/sites/{site}/monitors', [MonitorController::class, 'store'])
        ->name('sites.monitors.store');

    Route::get('/sites/{site}/monitors/{site_monitor}/edit', [MonitorController::class, 'edit'])
        ->name('sites.monitors.edit');

    Route::put('/sites/{site}/monitors/{site_monitor}', [MonitorController::class, 'update'])
        ->name('sites.monitors.update');

    Route::patch('/sites/{site}/monitors/{site_monitor}/toggle', [MonitorController::class, 'toggle'])
        ->name('sites.monitors.toggle');

    Route::delete('/sites/{site}/monitors/{site_monitor}', [MonitorController::class, 'destroy'])
        ->name('sites.monitors.destroy');
});

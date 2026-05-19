<?php

use App\Modules\MonitoredResources\Presentation\Http\Controllers\MonitoredResourceController;
use App\Modules\WorkerGateway\Presentation\Http\Controllers\SiteManualCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/sites', [MonitoredResourceController::class, 'index'])
        ->name('sites.index');

    Route::get('/sites/create', [MonitoredResourceController::class, 'create'])
        ->name('sites.create');

    Route::post('/sites', [MonitoredResourceController::class, 'store'])
        ->name('sites.store');

    Route::get('/sites/{site}', [MonitoredResourceController::class, 'show'])
        ->name('sites.show');

    Route::post('/sites/{site}/check-now', [SiteManualCheckController::class, 'store'])
        ->name('sites.check-now');

    Route::delete('/sites/{site}', [MonitoredResourceController::class, 'destroy'])
        ->name('sites.destroy');
});

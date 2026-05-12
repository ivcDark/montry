<?php

use App\Modules\MonitoredResources\Presentation\Http\Controllers\MonitoredResourceController;
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
});

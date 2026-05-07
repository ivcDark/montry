<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/sites', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'index'])
        ->name('sites.index');

    Route::get('/sites/create', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'create'])
        ->name('sites.create');

    Route::post('/sites', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'store'])
        ->name('sites.store');

    Route::get('/sites/{site}', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'show'])
        ->name('sites.show');

    Route::get('/sites/{site}/monitors/create', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'create'])
        ->name('sites.monitors.create');

    Route::post('/sites/{site}/monitors', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'store'])
        ->name('sites.monitors.store');

    Route::get('/sites/{site}/monitors/{monitor}/edit', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'edit'])
        ->name('sites.monitors.edit');

    Route::put('/sites/{site}/monitors/{monitor}', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'update'])
        ->name('sites.monitors.update');
});

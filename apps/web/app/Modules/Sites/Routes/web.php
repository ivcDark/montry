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

    Route::get('/sites/{site}/monitors/{site_monitor}/edit', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'edit'])
        ->name('sites.monitors.edit');

    Route::put('/sites/{site}/monitors/{site_monitor}', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'update'])
        ->name('sites.monitors.update');

    Route::patch('/sites/{site}/monitors/{site_monitor}/toggle', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'toggle'])
        ->name('sites.monitors.toggle');

    Route::delete('/sites/{site}/monitors/{site_monitor}', [\App\Modules\Sites\Http\Controllers\SiteMonitorController::class, 'destroy'])
        ->name('sites.monitors.destroy');

    Route::get('/sites/folders/create', [\App\Modules\Sites\Http\Controllers\FolderController::class, 'create'])
        ->name('sites.folders.create');

    Route::post('/sites/folders', [\App\Modules\Sites\Http\Controllers\FolderController::class, 'store'])
        ->name('sites.folders.store');
});

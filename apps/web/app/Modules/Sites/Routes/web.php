<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/sites', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'index'])
        ->name('sites.index');

    Route::get('/sites/create', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'create'])
        ->name('sites.create');

    Route::post('/sites', [\App\Modules\Sites\Http\Controllers\IndexController::class, 'store'])
        ->name('sites.store');
});

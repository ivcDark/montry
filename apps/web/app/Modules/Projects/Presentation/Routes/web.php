<?php

use App\Modules\Projects\Presentation\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])
        ->name('projects.index');

    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->name('projects.create');

    Route::post('/projects', [ProjectController::class, 'store'])
        ->name('projects.store');

    Route::get('/sites/folders/create', [ProjectController::class, 'create'])
        ->name('sites.folders.create');

    Route::post('/sites/folders', [ProjectController::class, 'store'])
        ->name('sites.folders.store');
});

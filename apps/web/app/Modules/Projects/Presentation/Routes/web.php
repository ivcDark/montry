<?php

use App\Modules\Projects\Presentation\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/sites/folders/create', [ProjectController::class, 'create'])
        ->name('sites.folders.create');

    Route::post('/sites/folders', [ProjectController::class, 'store'])
        ->name('sites.folders.store');
});

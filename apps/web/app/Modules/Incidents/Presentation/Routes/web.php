<?php

use App\Modules\Incidents\Presentation\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/incidents', [IncidentController::class, 'index'])
        ->name('incidents.index');
});

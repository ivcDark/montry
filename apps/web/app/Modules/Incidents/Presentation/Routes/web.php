<?php

use App\Modules\Incidents\Presentation\Http\Controllers\IncidentController;
use App\Modules\Incidents\Presentation\Http\Controllers\WeeklyDigestPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/incidents', [IncidentController::class, 'index'])
        ->name('incidents.index');

    Route::put('/incidents/weekly-digest-preference', [WeeklyDigestPreferenceController::class, 'update'])
        ->name('incidents.weekly-digest-preference.update');
});

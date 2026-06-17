<?php

use App\Modules\Reports\Presentation\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');
});

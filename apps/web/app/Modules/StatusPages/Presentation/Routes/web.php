<?php

use App\Modules\StatusPages\Presentation\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/status-pages', [StatusPageController::class, 'index'])->name('status-pages.index');
    Route::get('/status-pages/create', [StatusPageController::class, 'create'])->name('status-pages.create');
    Route::post('/status-pages', [StatusPageController::class, 'store'])->name('status-pages.store');
    Route::get('/status-pages/{statusPage}/edit', [StatusPageController::class, 'edit'])->name('status-pages.edit');
    Route::put('/status-pages/{statusPage}', [StatusPageController::class, 'update'])->name('status-pages.update');
    Route::delete('/status-pages/{statusPage}', [StatusPageController::class, 'destroy'])->name('status-pages.destroy');
    Route::get('/status-pages/{statusPage}/preview', [StatusPageController::class, 'preview'])->name('status-pages.preview');
});

Route::get('/status/{slug}', [StatusPageController::class, 'show'])->name('status-pages.public');

<?php

use App\Modules\Billing\Presentation\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');

    Route::post('/billing/checkout', [BillingController::class, 'checkout'])
        ->name('billing.checkout');

    Route::post('/billing/schedule-downgrade', [BillingController::class, 'scheduleDowngrade'])
        ->name('billing.schedule-downgrade');

    Route::get('/billing/payments/{payment}', [BillingController::class, 'payment'])
        ->name('billing.payments.show');

    Route::get('/billing/payments/{payment}/fake-bank', [BillingController::class, 'fakeBank'])
        ->name('billing.payments.fake-bank');

    Route::post('/billing/payments/{payment}/confirm', [BillingController::class, 'confirm'])
        ->name('billing.payments.confirm');
});

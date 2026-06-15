<?php

use App\Modules\Billing\Presentation\Http\Controllers\BillingController;
use App\Modules\Billing\Presentation\Http\Controllers\RobokassaController;
use App\Modules\Billing\Presentation\Http\Controllers\YooKassaController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/billing/robokassa/result', [RobokassaController::class, 'result'])
    ->name('billing.robokassa.result');

Route::match(['get', 'post'], '/billing/robokassa/success', [RobokassaController::class, 'success'])
    ->name('billing.robokassa.success');

Route::match(['get', 'post'], '/billing/robokassa/fail', [RobokassaController::class, 'fail'])
    ->name('billing.robokassa.fail');

Route::post('/billing/yookassa/webhook', [YooKassaController::class, 'webhook'])
    ->name('billing.yookassa.webhook');

Route::get('/billing/yookassa/return/{payment}', [YooKassaController::class, 'return'])
    ->name('billing.yookassa.return');

Route::middleware('auth')->group(function (): void {
    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');

    Route::post('/billing/checkout', [BillingController::class, 'checkout'])
        ->name('billing.checkout');

    Route::post('/billing/schedule-downgrade', [BillingController::class, 'scheduleDowngrade'])
        ->name('billing.schedule-downgrade');

    Route::get('/billing/payments/{payment}', [BillingController::class, 'payment'])
        ->name('billing.payments.show');

    Route::post('/billing/payments/{payment}/robokassa/test-success', [RobokassaController::class, 'testSuccess'])
        ->name('billing.payments.robokassa.test-success');

    Route::post('/billing/payments/{payment}/yookassa/checkout', [YooKassaController::class, 'checkout'])
        ->name('billing.payments.yookassa.checkout');

    Route::get('/billing/payments/{payment}/fake-bank', [BillingController::class, 'fakeBank'])
        ->name('billing.payments.fake-bank');

    Route::post('/billing/payments/{payment}/confirm', [BillingController::class, 'confirm'])
        ->name('billing.payments.confirm');
});

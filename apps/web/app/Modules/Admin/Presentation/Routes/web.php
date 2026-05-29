<?php

use App\Modules\Admin\Infrastructure\Http\Middleware\EnsureAdmin;
use App\Modules\Admin\Presentation\Http\Controllers\AdminPlanController;
use App\Modules\Admin\Presentation\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::redirect('/', '/admin/users')
            ->name('index');

        Route::get('/plans', [AdminPlanController::class, 'index'])
            ->name('plans.index');

        Route::post('/plans', [AdminPlanController::class, 'store'])
            ->name('plans.store');

        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])
            ->name('plans.update');

        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])
            ->name('plans.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/{user}', [AdminUserController::class, 'show'])
            ->name('users.show');

        Route::patch('/users/{user}/block', [AdminUserController::class, 'toggleBlock'])
            ->name('users.block');

        Route::patch('/users/{user}/organizations/{organization}/plan', [AdminUserController::class, 'updatePlan'])
            ->name('users.organizations.plan');
    });

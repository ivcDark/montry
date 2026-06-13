<?php

namespace App\Modules\Billing\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BillingModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('web')
            ->group(app_path('Modules/Billing/Presentation/Routes/web.php'));
    }
}

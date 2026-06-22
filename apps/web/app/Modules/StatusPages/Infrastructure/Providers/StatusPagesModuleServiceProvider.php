<?php

namespace App\Modules\StatusPages\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StatusPagesModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../../Presentation/Routes/web.php');
    }
}

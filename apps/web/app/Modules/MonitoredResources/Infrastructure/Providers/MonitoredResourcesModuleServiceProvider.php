<?php

namespace App\Modules\MonitoredResources\Infrastructure\Providers;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MonitoredResourcesModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::model('site', MonitoredResource::class);

        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Routes/web.php');
    }
}

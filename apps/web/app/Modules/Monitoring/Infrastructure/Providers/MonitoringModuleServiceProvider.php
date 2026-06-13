<?php

namespace App\Modules\Monitoring\Infrastructure\Providers;

use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\CheckResultRepositoryInterface;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Monitoring\Infrastructure\Repositories\EloquentCheckResultRepository;
use App\Modules\Monitoring\Infrastructure\Repositories\EloquentMonitorRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class MonitoringModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CheckTypeRegistry::class);

        $this->app->bind(
            MonitorRepositoryInterface::class,
            EloquentMonitorRepository::class,
        );

        $this->app->bind(
            CheckResultRepositoryInterface::class,
            EloquentCheckResultRepository::class,
        );
    }

    public function boot(): void
    {
        Route::model('site_monitor', Monitor::class);
        Route::model('monitor', Monitor::class);

        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Routes/web.php');
    }
}

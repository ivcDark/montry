<?php

namespace App\Modules\WorkerGateway\Infrastructure\Providers;

use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;
use App\Modules\WorkerGateway\Infrastructure\Clients\HttpMonitoringWorkerClient;
use App\Modules\WorkerGateway\Infrastructure\Clients\NullMonitoringWorkerClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WorkerGatewayModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MonitoringWorkerClientInterface::class, function () {
            $baseUrl = config('services.poller.base_url');

            if (config('services.poller.mock') || $baseUrl === null || $baseUrl === '') {
                return new NullMonitoringWorkerClient();
            }

            return new HttpMonitoringWorkerClient(
                baseUrl: $baseUrl,
                token: config('services.poller.token'),
            );
        });
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('internal')
            ->group(__DIR__ . '/../../Presentation/Routes/internal.php');
    }
}

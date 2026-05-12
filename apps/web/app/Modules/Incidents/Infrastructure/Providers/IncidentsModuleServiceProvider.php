<?php

namespace App\Modules\Incidents\Infrastructure\Providers;

use App\Modules\Incidents\Application\Listeners\UpdateIncidentStateForCheckResult;
use App\Modules\Monitoring\Domain\Events\CheckResultReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IncidentsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Event::listen(CheckResultReceived::class, UpdateIncidentStateForCheckResult::class);

        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Routes/web.php');
    }
}

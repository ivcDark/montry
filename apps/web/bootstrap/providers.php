<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    \App\Modules\Identity\Infrastructure\Providers\IdentityModuleServiceProvider::class,
    \App\Modules\Billing\Infrastructure\Providers\BillingModuleServiceProvider::class,
    \App\Modules\Projects\Infrastructure\Providers\ProjectsModuleServiceProvider::class,
    \App\Modules\Dashboard\Infrastructure\Providers\DashboardModuleServiceProvider::class,
    \App\Modules\MonitoredResources\Infrastructure\Providers\MonitoredResourcesModuleServiceProvider::class,
    \App\Modules\Monitoring\Infrastructure\Providers\MonitoringModuleServiceProvider::class,
    \App\Modules\CheckTypes\Infrastructure\Providers\CheckTypesModuleServiceProvider::class,
    \App\Modules\Incidents\Infrastructure\Providers\IncidentsModuleServiceProvider::class,
    \App\Modules\Notifications\Infrastructure\Providers\NotificationsModuleServiceProvider::class,
    \App\Modules\Reports\Infrastructure\Providers\ReportsModuleServiceProvider::class,
    \App\Modules\WorkerGateway\Infrastructure\Providers\WorkerGatewayModuleServiceProvider::class,

    \App\Modules\Auth\Providers\AuthModuleServiceProvider::class,
    \App\Modules\Organizations\Providers\OrganizationsModuleServiceProvider::class,
];

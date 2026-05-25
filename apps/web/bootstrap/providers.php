<?php

use App\Modules\Admin\Infrastructure\Providers\AdminModuleServiceProvider;
use App\Modules\Auth\Providers\AuthModuleServiceProvider;
use App\Modules\Billing\Infrastructure\Providers\BillingModuleServiceProvider;
use App\Modules\CheckTypes\Infrastructure\Providers\CheckTypesModuleServiceProvider;
use App\Modules\Dashboard\Infrastructure\Providers\DashboardModuleServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityModuleServiceProvider;
use App\Modules\Incidents\Infrastructure\Providers\IncidentsModuleServiceProvider;
use App\Modules\MonitoredResources\Infrastructure\Providers\MonitoredResourcesModuleServiceProvider;
use App\Modules\Monitoring\Infrastructure\Providers\MonitoringModuleServiceProvider;
use App\Modules\Notifications\Infrastructure\Providers\NotificationsModuleServiceProvider;
use App\Modules\Organizations\Providers\OrganizationsModuleServiceProvider;
use App\Modules\Projects\Infrastructure\Providers\ProjectsModuleServiceProvider;
use App\Modules\Reports\Infrastructure\Providers\ReportsModuleServiceProvider;
use App\Modules\Sites\Providers\SitesModuleServiceProvider;
use App\Modules\WorkerGateway\Infrastructure\Providers\WorkerGatewayModuleServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    IdentityModuleServiceProvider::class,
    BillingModuleServiceProvider::class,
    ProjectsModuleServiceProvider::class,
    DashboardModuleServiceProvider::class,
    MonitoredResourcesModuleServiceProvider::class,
    MonitoringModuleServiceProvider::class,
    CheckTypesModuleServiceProvider::class,
    IncidentsModuleServiceProvider::class,
    NotificationsModuleServiceProvider::class,
    ReportsModuleServiceProvider::class,
    WorkerGatewayModuleServiceProvider::class,
    AdminModuleServiceProvider::class,

    AuthModuleServiceProvider::class,
    OrganizationsModuleServiceProvider::class,
    SitesModuleServiceProvider::class,
];

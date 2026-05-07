<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    \App\Modules\Auth\Providers\AuthModuleServiceProvider::class,
    \App\Modules\Organizations\Providers\OrganizationsModuleServiceProvider::class,
];

<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    \app\Modules\Auth\Providers\AuthModuleServiceProvider::class,
];

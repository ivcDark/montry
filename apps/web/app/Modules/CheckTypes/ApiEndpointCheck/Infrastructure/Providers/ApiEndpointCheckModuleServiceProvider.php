<?php

namespace App\Modules\CheckTypes\ApiEndpointCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\ApiEndpointCheck\Domain\ApiEndpointCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class ApiEndpointCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new ApiEndpointCheckTypeDefinition());
    }
}

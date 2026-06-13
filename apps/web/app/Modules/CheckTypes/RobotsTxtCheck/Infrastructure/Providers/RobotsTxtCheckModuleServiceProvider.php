<?php

namespace App\Modules\CheckTypes\RobotsTxtCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\RobotsTxtCheck\Domain\RobotsTxtCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class RobotsTxtCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new RobotsTxtCheckTypeDefinition());
    }
}

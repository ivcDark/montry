<?php

namespace App\Modules\CheckTypes\DomainCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\DomainCheck\Domain\DomainCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class DomainCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new DomainCheckTypeDefinition());
    }
}

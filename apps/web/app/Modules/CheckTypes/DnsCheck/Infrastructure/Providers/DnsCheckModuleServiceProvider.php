<?php

namespace App\Modules\CheckTypes\DnsCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\DnsCheck\Domain\DnsCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class DnsCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new DnsCheckTypeDefinition());
    }
}

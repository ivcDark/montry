<?php

namespace App\Modules\CheckTypes\TcpPortCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\TcpPortCheck\Domain\TcpPortCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class TcpPortCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new TcpPortCheckTypeDefinition());
    }
}

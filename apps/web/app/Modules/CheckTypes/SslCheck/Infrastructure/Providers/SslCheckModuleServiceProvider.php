<?php

namespace App\Modules\CheckTypes\SslCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\SslCheck\Domain\SslCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class SslCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new SslCheckTypeDefinition());
    }
}

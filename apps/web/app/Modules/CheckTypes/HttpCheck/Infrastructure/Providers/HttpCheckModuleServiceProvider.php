<?php

namespace App\Modules\CheckTypes\HttpCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\HttpCheck\Domain\HttpCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class HttpCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new HttpCheckTypeDefinition());
    }
}

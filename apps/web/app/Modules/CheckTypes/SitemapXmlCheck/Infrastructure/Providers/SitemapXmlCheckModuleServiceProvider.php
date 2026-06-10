<?php

namespace App\Modules\CheckTypes\SitemapXmlCheck\Infrastructure\Providers;

use App\Modules\CheckTypes\SitemapXmlCheck\Domain\SitemapXmlCheckTypeDefinition;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class SitemapXmlCheckModuleServiceProvider extends ServiceProvider
{
    public function boot(CheckTypeRegistry $registry): void
    {
        $registry->register(new SitemapXmlCheckTypeDefinition());
    }
}

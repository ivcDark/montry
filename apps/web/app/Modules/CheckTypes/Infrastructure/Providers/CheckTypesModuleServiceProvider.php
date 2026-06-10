<?php

namespace App\Modules\CheckTypes\Infrastructure\Providers;

use App\Modules\CheckTypes\ApiEndpointCheck\Infrastructure\Providers\ApiEndpointCheckModuleServiceProvider;
use App\Modules\CheckTypes\DnsCheck\Infrastructure\Providers\DnsCheckModuleServiceProvider;
use App\Modules\CheckTypes\DomainCheck\Infrastructure\Providers\DomainCheckModuleServiceProvider;
use App\Modules\CheckTypes\HttpCheck\Infrastructure\Providers\HttpCheckModuleServiceProvider;
use App\Modules\CheckTypes\RobotsTxtCheck\Infrastructure\Providers\RobotsTxtCheckModuleServiceProvider;
use App\Modules\CheckTypes\SitemapXmlCheck\Infrastructure\Providers\SitemapXmlCheckModuleServiceProvider;
use App\Modules\CheckTypes\SslCheck\Infrastructure\Providers\SslCheckModuleServiceProvider;
use App\Modules\CheckTypes\TcpPortCheck\Infrastructure\Providers\TcpPortCheckModuleServiceProvider;
use Illuminate\Support\ServiceProvider;

final class CheckTypesModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(HttpCheckModuleServiceProvider::class);
        $this->app->register(SslCheckModuleServiceProvider::class);
        $this->app->register(DomainCheckModuleServiceProvider::class);
        $this->app->register(DnsCheckModuleServiceProvider::class);
        $this->app->register(RobotsTxtCheckModuleServiceProvider::class);
        $this->app->register(SitemapXmlCheckModuleServiceProvider::class);
        $this->app->register(ApiEndpointCheckModuleServiceProvider::class);
        $this->app->register(TcpPortCheckModuleServiceProvider::class);
    }

    public function boot(): void
    {
    }
}

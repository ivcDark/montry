<?php

namespace App\Modules\CheckTypes\Infrastructure\Providers;

use App\Modules\CheckTypes\DomainCheck\Infrastructure\Providers\DomainCheckModuleServiceProvider;
use App\Modules\CheckTypes\HttpCheck\Infrastructure\Providers\HttpCheckModuleServiceProvider;
use App\Modules\CheckTypes\SslCheck\Infrastructure\Providers\SslCheckModuleServiceProvider;
use Illuminate\Support\ServiceProvider;

final class CheckTypesModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(HttpCheckModuleServiceProvider::class);
        $this->app->register(SslCheckModuleServiceProvider::class);
        $this->app->register(DomainCheckModuleServiceProvider::class);
    }

    public function boot(): void
    {
    }
}

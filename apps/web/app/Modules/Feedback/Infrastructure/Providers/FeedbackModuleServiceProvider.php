<?php

namespace App\Modules\Feedback\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FeedbackModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../Presentation/Routes/web.php');
    }
}

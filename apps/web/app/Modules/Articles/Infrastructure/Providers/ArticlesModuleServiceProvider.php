<?php

namespace App\Modules\Articles\Infrastructure\Providers;

use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ArticlesModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::model('article', Article::class);

        Route::middleware('web')
            ->group(__DIR__.'/../../Presentation/Routes/web.php');
    }
}

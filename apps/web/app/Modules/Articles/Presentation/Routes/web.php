<?php

use App\Modules\Articles\Presentation\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/articles', [ArticleController::class, 'index'])
    ->name('articles.index');

Route::get('/articles/{slug}', [ArticleController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('articles.show');

<?php

use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/offers', function () {
    return Inertia::render('Legal/Offer');
})->name('offer');

Route::redirect('/offer', '/offers');

Route::get('/', function () {
    $plans = Plan::query()
        ->with('limits')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('price_cents')
        ->get()
        ->map(fn (Plan $plan): array => [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'sort_order' => $plan->sort_order,
            'limits' => $plan->limits
                ->mapWithKeys(fn ($limit): array => [$limit->key => $limit->value])
                ->all(),
        ])
        ->values();

    $articles = Article::query()
        ->published()
        ->orderBy('sort_order')
        ->orderByDesc('published_at')
        ->orderByDesc('created_at')
        ->limit(3)
        ->get()
        ->map(fn (Article $article): array => [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'published_at' => $article->published_at?->toIso8601String(),
        ])
        ->values();

    return Inertia::render('Welcome', [
        'plans' => $plans,
        'articles' => $articles,
    ]);
})->name('home');

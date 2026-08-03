<?php

namespace App\Modules\Articles\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    public function index(): Response
    {
        $articles = Article::query()
            ->published()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Article $article): array => $this->articlePayload($article, includeBody: false))
            ->values();

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
        ]);
    }

    public function show(string $slug): Response
    {
        $article = Article::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Article::query()
            ->published()
            ->where('id', '!=', $article->id)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Article $relatedArticle): array => $this->articlePayload($relatedArticle, includeBody: false))
            ->values();

        return Inertia::render('Articles/Show', [
            'article' => $this->articlePayload($article, includeBody: true),
            'related' => $related,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(Article $article, bool $includeBody): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $includeBody ? $article->body : null,
            'published_at' => $article->published_at?->toIso8601String(),
        ];
    }
}

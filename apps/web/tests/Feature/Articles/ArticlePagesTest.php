<?php

namespace Tests\Feature\Articles;

use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ArticlePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_page_shows_only_published_articles(): void
    {
        Article::query()->create([
            'title' => 'Опубликованная статья',
            'slug' => 'published-article',
            'excerpt' => 'Короткий анонс опубликованной статьи.',
            'body' => 'Текст опубликованной статьи.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        Article::query()->create([
            'title' => 'Скрытая статья',
            'slug' => 'hidden-article',
            'excerpt' => 'Короткий анонс скрытой статьи.',
            'body' => 'Текст скрытой статьи.',
            'is_published' => false,
        ]);

        $this
            ->get('/articles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Articles/Index', false)
                ->has('articles', 1)
                ->where('articles.0.slug', 'published-article')
            );
    }

    public function test_hidden_article_cannot_be_opened_publicly(): void
    {
        Article::query()->create([
            'title' => 'Скрытая статья',
            'slug' => 'hidden-article',
            'excerpt' => 'Короткий анонс скрытой статьи.',
            'body' => 'Текст скрытой статьи.',
            'is_published' => false,
        ]);

        $this
            ->get('/articles/hidden-article')
            ->assertNotFound();
    }
}

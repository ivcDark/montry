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

    public function test_article_page_includes_related_published_articles(): void
    {
        $current = Article::query()->create([
            'title' => 'Текущая статья',
            'slug' => 'current-article',
            'excerpt' => 'Анонс текущей статьи.',
            'body' => "## Заголовок\n\nТекст текущей статьи.\n\n- пункт один\n- пункт два",
            'is_published' => true,
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        Article::query()->create([
            'title' => 'Связанная статья',
            'slug' => 'related-article',
            'excerpt' => 'Анонс связанной статьи.',
            'body' => 'Текст связанной статьи.',
            'is_published' => true,
            'published_at' => now()->subDays(2),
            'sort_order' => 2,
        ]);

        Article::query()->create([
            'title' => 'Скрытая статья',
            'slug' => 'hidden-related',
            'excerpt' => 'Анонс скрытой статьи.',
            'body' => 'Текст скрытой статьи.',
            'is_published' => false,
            'sort_order' => 3,
        ]);

        $this
            ->get('/articles/'.$current->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Articles/Show', false)
                ->where('article.slug', 'current-article')
                ->has('related', 1)
                ->where('related.0.slug', 'related-article')
            );
    }
}

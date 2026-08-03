<?php

namespace Tests\Feature\Seo;

use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_allows_public_pages_when_indexable(): void
    {
        config([
            'seo.indexable' => true,
            'app.url' => 'https://montry.ru',
        ]);

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = (string) $response->getContent();

        $this->assertStringContainsString("Allow: /\n", $body);
        $this->assertStringContainsString("Disallow: /dashboard\n", $body);
        $this->assertStringContainsString("Disallow: /login\n", $body);
        $this->assertStringContainsString("Sitemap: https://montry.ru/sitemap.xml\n", $body);
        $this->assertDoesNotMatchRegularExpression('/^Disallow: \/$/m', $body);
    }

    public function test_robots_txt_blocks_everything_when_not_indexable(): void
    {
        config([
            'seo.indexable' => false,
            'app.url' => 'https://montry.ru',
        ]);

        $response = $this->get('/robots.txt');

        $response->assertOk();

        $body = (string) $response->getContent();

        $this->assertMatchesRegularExpression('/^Disallow: \/$/m', $body);
        $this->assertStringNotContainsString('Sitemap:', $body);
    }

    public function test_sitemap_includes_marketing_pages_and_published_articles(): void
    {
        config(['app.url' => 'https://montry.ru']);

        Article::query()->create([
            'title' => 'Опубликованная статья',
            'slug' => 'published-article',
            'excerpt' => 'Анонс.',
            'body' => 'Текст.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        Article::query()->create([
            'title' => 'Скрытая статья',
            'slug' => 'hidden-article',
            'excerpt' => 'Анонс.',
            'body' => 'Текст.',
            'is_published' => false,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('https://montry.ru/', false);
        $response->assertSee('https://montry.ru/articles', false);
        $response->assertSee('https://montry.ru/offers', false);
        $response->assertSee('https://montry.ru/user-agreement', false);
        $response->assertSee('https://montry.ru/articles/published-article', false);
        $response->assertDontSee('https://montry.ru/articles/hidden-article', false);
    }
}

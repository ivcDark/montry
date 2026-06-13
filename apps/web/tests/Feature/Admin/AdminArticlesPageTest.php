<?php

namespace Tests\Feature\Admin;

use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminArticlesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_open_admin_articles_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this
            ->actingAs($user)
            ->get('/admin/articles')
            ->assertForbidden();
    }

    public function test_admin_can_open_articles_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        Article::query()->create([
            'title' => 'Статья про мониторинг',
            'slug' => 'monitoring-article',
            'excerpt' => 'Короткий анонс статьи.',
            'body' => 'Основной текст статьи.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/articles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Articles/Index', false)
                ->has('articles', 1)
                ->where('articles.0.slug', 'monitoring-article')
            );
    }

    public function test_admin_can_create_and_hide_article(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post('/admin/articles', [
                'title' => 'Новая статья',
                'slug' => 'new-article',
                'excerpt' => 'Короткий анонс новой статьи.',
                'body' => 'Основной текст новой статьи.',
                'is_published' => true,
                'published_at' => now()->format('Y-m-d\TH:i'),
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $article = Article::query()->where('slug', 'new-article')->firstOrFail();

        $this->assertTrue($article->is_published);

        $this
            ->actingAs($admin)
            ->patch("/admin/articles/{$article->id}/toggle")
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'is_published' => false,
        ]);
    }
}

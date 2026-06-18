<?php

namespace App\Modules\Admin\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminArticleController extends Controller
{
    public function index(): Response
    {
        $articles = Article::query()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Article $article): array => $this->articlePayload($article))
            ->values();

        return Inertia::render('Admin/Articles/Index', [
            'articles' => $articles,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validatedArticleData($request);
        $article = Article::query()->create($this->articleAttributes($validated));

        $audit->record(
            category: 'admin',
            action: 'admin.article.created',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'article',
            targetId: (string) $article->id,
            source: 'admin',
            metadata: ['slug' => $article->slug],
        );

        return back()->with('success', "Статья «{$article->title}» создана.");
    }

    public function update(Request $request, Article $article, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validatedArticleData($request, $article);
        $previous = $article->only(['title', 'slug', 'is_published', 'published_at', 'sort_order']);

        $article->update($this->articleAttributes($validated, $article));
        $article->refresh();

        $audit->record(
            category: 'admin',
            action: 'admin.article.updated',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'article',
            targetId: (string) $article->id,
            source: 'admin',
            metadata: [
                'previous' => $previous,
                'current' => $article->only(['title', 'slug', 'is_published', 'published_at', 'sort_order']),
            ],
        );

        return back()->with('success', "Статья «{$article->title}» обновлена.");
    }

    public function toggle(Request $request, Article $article, AuditLogger $audit): RedirectResponse
    {
        $article->update([
            'is_published' => ! $article->is_published,
            'published_at' => $article->is_published ? $article->published_at : ($article->published_at ?? now()),
        ]);

        $audit->record(
            category: 'admin',
            action: $article->is_published ? 'admin.article.published' : 'admin.article.hidden',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'article',
            targetId: (string) $article->id,
            source: 'admin',
            metadata: ['slug' => $article->slug],
        );

        return back()->with(
            'success',
            $article->is_published
                ? "Статья «{$article->title}» опубликована."
                : "Статья «{$article->title}» скрыта.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedArticleData(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($article?->id),
            ],
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function articleAttributes(array $validated, ?Article $article = null): array
    {
        $isPublished = (bool) $validated['is_published'];

        return [
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug((string) ($validated['slug'] ?: $validated['title']), $article),
            'excerpt' => $validated['excerpt'],
            'body' => $validated['body'],
            'is_published' => $isPublished,
            'published_at' => $validated['published_at'] ?? ($isPublished ? now() : null),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function uniqueSlug(string $value, ?Article $article = null): string
    {
        $baseSlug = Str::slug($value) ?: 'article';
        $slug = $baseSlug;
        $counter = 2;

        while (
            Article::query()
                ->where('slug', $slug)
                ->when($article !== null, fn ($query) => $query->whereKeyNot($article->id))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'is_published' => $article->is_published,
            'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
            'sort_order' => $article->sort_order,
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
            'form' => [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'is_published' => $article->is_published,
                'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
                'sort_order' => $article->sort_order,
            ],
        ];
    }
}

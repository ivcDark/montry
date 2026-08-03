<?php

namespace App\Shared\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Articles\Infrastructure\Persistence\Models\Article;
use Illuminate\Http\Response;

final class SeoController extends Controller
{
    public function robots(): Response
    {
        $indexable = (bool) config('seo.indexable');
        $sitemapUrl = rtrim((string) config('app.url'), '/').'/sitemap.xml';

        if (! $indexable) {
            $body = implode("\n", [
                'User-agent: *',
                'Disallow: /',
                '',
            ]);

            return response($body, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /sites',
            'Disallow: /projects',
            'Disallow: /monitors',
            'Disallow: /incidents',
            'Disallow: /reports',
            'Disallow: /settings',
            'Disallow: /billing',
            'Disallow: /status-pages',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /internal',
            '',
            'Sitemap: '.$sitemapUrl,
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $now = now()->toAtomString();

        $urls = [
            ['loc' => $baseUrl.'/', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $baseUrl.'/articles', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => $baseUrl.'/offers', 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => $baseUrl.'/user-agreement', 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $articles = Article::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('updated_at')
            ->get(['slug', 'published_at', 'updated_at']);

        foreach ($articles as $article) {
            $lastmod = ($article->updated_at ?? $article->published_at ?? now())->toAtomString();

            $urls[] = [
                'loc' => $baseUrl.'/articles/'.$article->slug,
                'lastmod' => $lastmod,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.e($url['lastmod'])."</lastmod>\n";
            $xml .= '    <changefreq>'.e($url['changefreq'])."</changefreq>\n";
            $xml .= '    <priority>'.e($url['priority'])."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}

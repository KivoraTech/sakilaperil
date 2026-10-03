<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Format;
use App\I18n\Routes;
use App\Queries\Public\News;
use App\Queries\Public\Projects;
use App\View\Seo;
use App\View\SiteRichText;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** app/[locale]/(site)/news/page.tsx and news/[slug]/page.tsx. */
final class NewsController extends SiteController
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $locale = $this->locale();
        $pageNum = max(1, (int) (ProjectsController::one($request->query('page')) ?? '1') ?: 1);
        $categorySlug = ProjectsController::one($request->query('category'));
        $categorySlug = $categorySlug !== null ? mb_substr($categorySlug, 0, 80) : null;
        $categories = News::categories($locale);
        $category = null;
        foreach ($categories as $c) {
            if ($c['slug'] === $categorySlug) {
                $category = $c;
            }
        }
        $news = News::list($locale, ['limit' => self::PER_PAGE, 'page' => $pageNum, 'categoryId' => $category['id'] ?? null]);
        if ($pageNum > 1 && $pageNum > $news['pageCount']) {
            return $this->notFound();
        }
        $items = $news['items'];
        $showFeature = $pageNum === 1 && $category === null && count($items) > 1;
        $feature = $showFeature ? $items[0] : null;
        $rest = $showFeature ? array_slice($items, 1) : $items;
        $meta = $this->meta(Seo::sameAlternates('/news'), t('news.title'), t('news.lead'));
        // Filtered/paginated variants are not separate documents for search engines.
        if ($request->query('page') !== null || $request->query('category') !== null) {
            $meta['robots'] = 'noindex, follow';
        }

        return response()->view('site.pages.news.index', [
            'locale' => $locale,
            'categories' => $categories,
            'category' => $category,
            'feature' => $feature,
            'rest' => $rest,
            'items' => $items,
            'pageNum' => $pageNum,
            'pageCount' => $news['pageCount'],
            'meta' => $meta,
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $post = News::get($locale, $slug);
        if ($post === null) {
            return $this->notFound();
        }
        $latest = News::list($locale, ['limit' => 4]);
        $project = ! empty($post['projectId']) ? (Projects::list($locale, ['ids' => [$post['projectId']]])[0] ?? null) : null;
        $related = array_slice(array_values(array_filter($latest['items'], fn ($p) => $p['id'] !== $post['id'])), 0, 3);
        $path = Routes::news($post['slug']);

        return response()->view('site.pages.news.show', [
            'locale' => $locale,
            'post' => $post,
            'project' => $project,
            'related' => $related,
            'path' => $path,
            'url' => Seo::absoluteUrl(Seo::localePath($locale, $path)),
            'minutes' => Format::readingMinutes(SiteRichText::countWords($post['body'])),
            'settings' => $this->settings(),
            'alternates' => $post['alternates'],
            'meta' => $this->meta($post['alternates'], $post['seoTitle'] ?? $post['title'], $post['seoDescription'] ?? $post['excerpt'], $post['coverImage'], 'article', $post['publishedAt']),
        ]);
    }
}

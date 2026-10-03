<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Routes;
use App\Queries\Public\Albums;
use App\Queries\Public\News;
use App\Queries\Public\Projects;
use App\View\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** app/[locale]/(site)/projects/page.tsx and projects/[slug]/page.tsx. */
final class ProjectsController extends SiteController
{
    private const PER_PAGE = 12;

    private const STAGES = ['active' => 'ACTIVE', 'completed' => 'COMPLETED', 'planned' => 'PLANNED'];

    public function index(Request $request): Response
    {
        $locale = $this->locale();
        $stageKey = self::one($request->query('stage'));
        $stage = $stageKey !== null && isset(self::STAGES[$stageKey]) ? self::STAGES[$stageKey] : null;
        $sector = self::one($request->query('sector'));
        $sector = $sector !== null ? mb_substr($sector, 0, 80) : null;
        $pageNum = max(1, (int) (self::one($request->query('page')) ?? '1') ?: 1);

        $all = Projects::list($locale);
        $sectors = array_values(array_unique(array_filter(array_map(fn ($p) => $p['sector'], $all))));
        $filtered = array_values(array_filter($all, fn ($p) => ($stage === null || $p['stage'] === $stage) && ($sector === null || $sector === '' || $p['sector'] === $sector)));
        $pageCount = max(1, (int) ceil(count($filtered) / self::PER_PAGE));
        if ($pageNum > 1 && $pageNum > $pageCount) {
            return $this->notFound();
        }
        $current = array_slice($filtered, ($pageNum - 1) * self::PER_PAGE, self::PER_PAGE);

        return response()->view('site.pages.projects.index', [
            'locale' => $locale,
            'stageKey' => $stageKey,
            'sector' => $sector,
            'sectors' => $sectors,
            'filtered' => $filtered,
            'current' => $current,
            'pageNum' => $pageNum,
            'pageCount' => $pageCount,
            'meta' => $this->meta(Seo::sameAlternates('/projects'), t('projects.title'), t('projects.lead')),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $project = Projects::get($locale, $slug);
        if ($project === null) {
            return $this->notFound();
        }
        $updates = News::list($locale, ['projectId' => $project['id'], 'limit' => 6]);
        $albums = Albums::list($locale, ['projectId' => $project['id'], 'limit' => 1]);
        $album = isset($albums[0]) ? Albums::get($locale, ['id' => $albums[0]['id']]) : null;
        $others = Projects::list($locale, ['limit' => 4]);
        $related = array_slice(array_values(array_filter($others, fn ($p) => $p['id'] !== $project['id'])), 0, 3);

        return response()->view('site.pages.projects.show', [
            'locale' => $locale,
            'project' => $project,
            'updates' => $updates,
            'album' => $album,
            'related' => $related,
            'alternates' => $project['alternates'],
            'donateHref' => '/donate?project='.rawurlencode($project['slug']),
            // Only projects that are running ask for support (not planned ones, not completed ones).
            'canDonate' => $project['stage'] === 'ACTIVE',
            'path' => Routes::project($project['slug']),
            'meta' => $this->meta($project['alternates'], $project['seoTitle'] ?? $project['title'], $project['seoDescription'] ?? $project['summary'], $project['coverImage']),
        ]);
    }

    /** First value of a query parameter (string|array). */
    public static function one(mixed $v): ?string
    {
        if (is_array($v)) {
            $v = $v[0] ?? null;
        }

        return is_string($v) ? $v : null;
    }
}

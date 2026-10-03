<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\I18n\Locales;
use App\Queries\Admin\Events;
use App\Queries\Admin\News;
use App\Queries\Admin\Projects;
use Illuminate\Contracts\View\View;

/**
 * Authenticated draft previews of content items (port of app/preview/[locale]/{news,projects,events}/[id]).
 * The SAVED state is rendered with the public site's chrome and components, whatever the status.
 * Route middleware: admin.auth + capability:content.create; SecurityHeaders adds noindex + no-store.
 */
final class ContentPreviewController
{
    public function news(string $locale, string $id): View
    {
        abort_unless(Locales::isLocale($locale), 404);
        $post = News::draftForPreview($id, $locale);
        abort_if($post === null, 404);

        return view('preview.content.news', self::common($locale, $post, 'News story', 'news', $id) + ['post' => $post]);
    }

    public function project(string $locale, string $id): View
    {
        abort_unless(Locales::isLocale($locale), 404);
        $project = Projects::draftForPreview($id, $locale);
        abort_if($project === null, 404);

        return view('preview.content.project', self::common($locale, $project, 'Project', 'projects', $id) + ['project' => $project]);
    }

    public function event(string $locale, string $id): View
    {
        abort_unless(Locales::isLocale($locale), 404);
        $event = Events::draftForPreview($id, $locale);
        abort_if($event === null, 404);

        return view('preview.content.event', self::common($locale, $event, 'Event', 'events', $id) + ['event' => $event]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function common(string $locale, array $item, string $kindLabel, string $segment, string $id): array
    {
        return [
            'locale' => $locale,
            'meta' => [
                'title' => 'Preview · '.$item['title'],
                'robots' => 'noindex, nofollow, nocache',
            ],
            'banner' => [
                'title' => $item['title'],
                'kindLabel' => $kindLabel,
                'status' => $item['status'],
                'isFallback' => $item['isFallback'],
                'editHref' => "/admin/{$segment}/{$id}",
                'previewPathFor' => array_combine(Locales::ALL, array_map(fn (string $l): string => "/preview/{$l}/{$segment}/{$id}", Locales::ALL)),
            ],
        ];
    }
}

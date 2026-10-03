<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Queries\Public\Albums;
use App\View\Seo;
use Illuminate\Http\Response;

/** app/[locale]/(site)/gallery/page.tsx and gallery/[slug]/page.tsx. */
final class GalleryController extends SiteController
{
    public function index(): Response
    {
        $locale = $this->locale();

        return response()->view('site.pages.gallery.index', [
            'locale' => $locale,
            'albums' => Albums::list($locale),
            'meta' => $this->meta(Seo::sameAlternates('/gallery'), t('gallery.title'), t('gallery.lead')),
        ]);
    }

    public function show(string $locale, string $slug): Response
    {
        $album = Albums::get($locale, ['slug' => $slug]);
        if ($album === null) {
            return $this->notFound();
        }
        $albums = Albums::list($locale);
        $idx = -1;
        foreach ($albums as $i => $a) {
            if ($a['id'] === $album['id']) {
                $idx = $i;
            }
        }
        $photos = count(array_filter($album['items'], fn ($i) => $i['type'] === 'PHOTO'));

        return response()->view('site.pages.gallery.show', [
            'locale' => $locale,
            'album' => $album,
            'prev' => $idx > 0 ? $albums[$idx - 1] : null,
            'next' => $idx >= 0 && $idx < count($albums) - 1 ? $albums[$idx + 1] : null,
            'photos' => $photos,
            'videos' => count($album['items']) - $photos,
            'alternates' => $album['alternates'],
            'meta' => $this->meta($album['alternates'], $album['title'], $album['description'], $album['coverImage']),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Locales;
use App\Media\Assets;
use App\Settings\SiteSettings;
use App\View\Seo;
use Illuminate\Http\Response;

/** Base for the public (Blade) site controllers. */
abstract class SiteController
{
    /** @return array<string, mixed> */
    protected function settings(): array
    {
        return app(SiteSettings::class)->get();
    }

    protected function locale(): string
    {
        return Locales::current();
    }

    /** The site 404 inside the chrome (app/[locale]/(site)/not-found.tsx). */
    protected function notFound(): Response
    {
        return response()->view('site.pages.not-found', [
            'locale' => $this->locale(),
            'meta' => ['title' => t('errors.notFoundTitle'), 'robots' => 'noindex'],
        ], 404);
    }

    /** @return array{url: string, width: ?int, height: ?int, alt: string}|null */
    protected function ogImage(?string $id): ?array
    {
        $id ??= $this->settings()['seo']['ogImageId'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        return Assets::byIds([$id], $this->locale())[$id] ?? null;
    }

    /**
     * Seo::build() with this request's locale and settings.
     *
     * @param  array<string, string>  $alternates
     * @param  array<string, mixed>|null  $image
     * @return array<string, mixed>
     */
    protected function meta(array $alternates, ?string $title = null, ?string $description = null, ?array $image = null, string $type = 'website', ?string $publishedTime = null, bool $noIndex = false): array
    {
        // Every page gets a share image: its own, or the default one from Settings → Google & sharing.
        $image ??= $this->ogImage(null);

        return Seo::build($this->locale(), $this->settings(), $alternates, $title, $description, $image, $type, $publishedTime, $noIndex);
    }
}

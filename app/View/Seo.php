<?php

declare(strict_types=1);

namespace App\View;

use App\I18n\Locales;
use App\Settings\SettingsTodo;

/**
 * Port of components/site/seo/metadata.ts. Produces the `$meta` array the public document layout
 * renders (same tags and order as Next's metadata output):
 *   ['title', 'description', 'robots', 'canonical', 'alternates' => [hreflang => url],
 *    'og' => [[property, content], …], 'twitter' => [[name, content], …]]
 */
final class Seo
{
    public static function siteUrl(): string
    {
        return rtrim((string) config('sakila.site_url'), '/');
    }

    /** "/it" + "/projects" → "/it/projects"; "/" → "/it". */
    public static function localePath(string $locale, string $path): string
    {
        return '/'.$locale.($path === '/' ? '' : $path);
    }

    public static function absoluteUrl(string $path): string
    {
        if (preg_match('/^https?:\/\//', $path) === 1) {
            return $path;
        }

        return self::siteUrl().(str_starts_with($path, '/') ? '' : '/').$path;
    }

    /**
     * Same path in every locale (collection lists).
     *
     * @return array<string, string>
     */
    public static function sameAlternates(string $path): array
    {
        return array_fill_keys(Locales::ALL, $path);
    }

    /**
     * Canonical + hreflang: locales without a translation are omitted, x-default → Italian.
     *
     * @param  array<string, string>  $alternates  locale-less paths per locale
     * @return array{canonical: string, languages: array<string, string>}
     */
    public static function languageAlternates(string $locale, array $alternates): array
    {
        $languages = [];
        foreach (Locales::ALL as $l) {
            if (! empty($alternates[$l])) {
                $languages[$l] = self::localePath($l, $alternates[$l]);
            }
        }
        $def = $alternates[Locales::DEFAULT] ?? null;
        if ($def) {
            $languages['x-default'] = self::localePath(Locales::DEFAULT, $def);
        }
        $own = $alternates[$locale] ?? $def ?? '/';

        return ['canonical' => self::localePath(! empty($alternates[$locale]) ? $locale : Locales::DEFAULT, $own), 'languages' => $languages];
    }

    /**
     * buildMetadata().
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $alternates
     * @param  array{url: string, width?: ?int, height?: ?int, alt?: ?string}|null  $image
     * @return array<string, mixed>
     */
    public static function build(
        string $locale,
        array $settings,
        array $alternates,
        ?string $title = null,
        ?string $description = null,
        ?array $image = null,
        string $type = 'website',
        ?string $publishedTime = null,
        bool $noIndex = false,
    ): array {
        $description = ($description !== null && $description !== '') ? $description : Locales::pick($settings['seo']['defaultDescription'] ?? null, $locale);
        $description = self::clip((string) $description);
        $plainTitle = ($title !== null && $title !== '') ? $title : Locales::pick($settings['seo']['defaultTitle'] ?? null, $locale);
        $alt = self::languageAlternates($locale, $alternates);
        $canonical = self::absoluteUrl($alt['canonical']);
        $languages = array_map(self::absoluteUrl(...), $alt['languages']);
        $ogLocale = fn (string $l): string => $l === 'it' ? 'it_IT' : 'en_GB';

        $og = [
            ['og:title', $plainTitle],
            ['og:description', $description],
            ['og:url', $canonical],
            ['og:site_name', (string) $settings['orgName']],
            ['og:locale', $ogLocale($locale)],
        ];
        if ($image !== null) {
            $og[] = ['og:image', self::absoluteUrl($image['url'])];
            if (! empty($image['width'])) {
                $og[] = ['og:image:width', (string) $image['width']];
            }
            if (! empty($image['height'])) {
                $og[] = ['og:image:height', (string) $image['height']];
            }
            if (($image['alt'] ?? '') !== '') {
                $og[] = ['og:image:alt', (string) $image['alt']];
            }
        }
        foreach (Locales::ALL as $l) {
            if ($l !== $locale) {
                $og[] = ['og:locale:alternate', $ogLocale($l)];
            }
        }
        $og[] = ['og:type', $type];
        if ($type === 'article' && $publishedTime) {
            $og[] = ['article:published_time', $publishedTime];
        }
        $twitter = [
            ['twitter:card', $image !== null ? 'summary_large_image' : 'summary'],
            ['twitter:title', $plainTitle],
            ['twitter:description', $description],
        ];
        if ($image !== null) {
            $twitter[] = ['twitter:image', self::absoluteUrl($image['url'])];
        }

        return [
            'title' => self::documentTitle($plainTitle, $settings, $locale, $title === null || $title === ''),
            'description' => $description,
            'robots' => $noIndex ? 'noindex, follow' : null,
            'canonical' => $canonical,
            'alternates' => $languages,
            'og' => $og,
            'twitter' => $twitter,
        ];
    }

    /**
     * `<title>`: the page title inside the "title pattern" setting (seo.titleTemplate, e.g.
     * "%s · Sakila per il Nepal"). The default title (home) and titles that already name the
     * organisation are left as they are; og:title / twitter:title keep the plain title.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function documentTitle(string $title, array $settings, string $locale, bool $isDefault = false): string
    {
        $template = Locales::pick($settings['seo']['titleTemplate'] ?? null, $locale);
        $org = (string) ($settings['orgName'] ?? '');
        if ($isDefault || ! str_contains($template, '%s') || ($org !== '' && mb_stripos($title, $org) !== false)) {
            return $title;
        }

        return str_replace('%s', $title, $template);
    }

    /** Search engines cut descriptions at about 155–160 characters: end on a whole word instead. */
    public static function clip(string $text, int $max = 158): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,;:.-–—').'…';
    }

    /** `@id` of the organisation node, so other nodes can reference it instead of repeating it. */
    public static function organizationId(): string
    {
        return self::siteUrl().'/#organization';
    }

    /**
     * schema.org WebSite (home page only).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function websiteJsonLd(array $settings, string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => self::siteUrl().'/#website',
            'url' => self::absoluteUrl(self::localePath($locale, '/')),
            'name' => $settings['orgName'],
            'inLanguage' => $locale,
            'publisher' => ['@id' => self::organizationId()],
        ];
    }

    /**
     * schema.org NewsArticle. Built here, not in Blade: `'@context'` inside a template is compiled
     * as Blade's own `@context` directive.
     *
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function newsArticleJsonLd(array $post, array $settings, string $locale, string $url, ?string $logoUrl = null): array
    {
        $org = ['@type' => 'NGO', '@id' => self::organizationId(), 'name' => $settings['orgName']];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $post['title'],
            'description' => $post['excerpt'] ?? null,
            'datePublished' => $post['publishedAt'] ?? null,
            'inLanguage' => ! empty($post['isFallback']) ? Locales::DEFAULT : $locale,
            'image' => ! empty($post['coverImage']) ? [self::absoluteUrl($post['coverImage']['url'])] : null,
            'author' => ! empty($post['authorName']) ? ['@type' => 'Person', 'name' => $post['authorName']] : $org,
            'publisher' => $org + ['logo' => $logoUrl !== null ? ['@type' => 'ImageObject', 'url' => self::absoluteUrl($logoUrl)] : null],
            'mainEntityOfPage' => $url,
        ];
    }

    /**
     * schema.org Event.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function eventJsonLd(array $event, array $settings, string $locale, string $url, ?string $online = null): array
    {
        $known = static fn (mixed $v): ?string => is_string($v) && $v !== '' && ! SettingsTodo::isTodo($v) ? $v : null;
        $place = $known($event['locationName'] ?? null);
        $address = $known($event['address'] ?? null);
        $hasPlace = ! empty($event['locationName']) || ! empty($event['address']);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['title'],
            'description' => $event['summary'] ?? null,
            'startDate' => $event['startsAt'],
            'endDate' => $event['endsAt'] ?? null,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $online ? ($hasPlace ? 'https://schema.org/MixedEventAttendanceMode' : 'https://schema.org/OnlineEventAttendanceMode') : 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $online && ! $hasPlace
                ? ['@type' => 'VirtualLocation', 'url' => $online]
                : ['@type' => 'Place', 'name' => $place ?? $settings['orgName'], 'address' => $address !== null ? ['@type' => 'PostalAddress', 'streetAddress' => $address] : null],
            'image' => ! empty($event['coverImage']) ? [self::absoluteUrl($event['coverImage']['url'])] : null,
            'organizer' => ['@type' => 'NGO', '@id' => self::organizationId(), 'name' => $settings['orgName'], 'url' => self::absoluteUrl(self::localePath($locale, '/'))],
            'url' => $url,
        ];
    }

    /**
     * schema.org FAQPage.
     *
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqJsonLd(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $i): array => ['@type' => 'Question', 'name' => $i['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['answer']]], $items),
        ];
    }

    /**
     * schema.org NGO for the whole site. TODO facts are left out, never invented.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function organizationJsonLd(array $settings, string $locale, ?string $logoUrl = null): array
    {
        $known = static fn (mixed $v): ?string => is_string($v) && $v !== '' && ! SettingsTodo::isTodo($v) ? $v : null;
        $address = $known(Locales::pick($settings['contact']['address'] ?? null, $locale));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'NGO',
            '@id' => self::organizationId(),
            'name' => $settings['orgName'],
            'legalName' => $known($settings['organization']['legalName'] ?? null),
            'taxID' => $known($settings['organization']['taxCode'] ?? null),
            'url' => self::absoluteUrl(self::localePath($locale, '/')),
            'logo' => $logoUrl !== null ? self::absoluteUrl($logoUrl) : null,
            'email' => $known($settings['contact']['email'] ?? null),
            'telephone' => $known($settings['contact']['phone'] ?? null),
            'foundingDate' => ! empty($settings['organization']['foundedYear']) ? (string) $settings['organization']['foundedYear'] : null,
            'address' => $address !== null ? ['@type' => 'PostalAddress', 'streetAddress' => $address] : null,
            'sameAs' => array_values(array_filter(array_map(fn ($s) => $s['url'] ?? '', $settings['socialLinks'] ?? []), fn ($u) => is_string($u) && $u !== '' && ! SettingsTodo::isTodo($u))) ?: null,
            'description' => Locales::pick($settings['seo']['defaultDescription'] ?? null, $locale),
        ];
    }

    /**
     * @param  list<array{name: string, path: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbJsonLd(array $items): array
    {
        $list = [];
        foreach ($items as $i => $it) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it['name'], 'item' => self::absoluteUrl($it['path'])];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }
}

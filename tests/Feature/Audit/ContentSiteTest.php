<?php

declare(strict_types=1);

use App\Storage\Storage;

/*
| The public site as it ships: the REAL dataset (database/content) is loaded once, then every URL
| of the sitemap is rendered in Italian and English. Guards the things the audit found by eye:
| placeholder text, remote demo images, missing or duplicated <h1>, broken structured data, the
| admin account shown as a news author, the import date shown as a news date.
*/

beforeEach(function () {
    $this->uploads = sys_get_temp_dir().'/sakila-real-site-'.bin2hex(random_bytes(4));
    config(['sakila.local_uploads_dir' => $this->uploads, 'sakila.storage_driver' => 'local']);
    Storage::reset();
});

afterEach(function () {
    if (is_dir($this->uploads)) {
        exec('rm -rf '.escapeshellarg($this->uploads));
    }
    Storage::reset();
});

/** @return list<string> the paths listed in sitemap.xml */
function sitemapPaths(string $xml): array
{
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);

    return array_values(array_unique(array_map(
        fn (string $loc): string => (string) (parse_url(html_entity_decode($loc), PHP_URL_PATH) ?: '/'),
        $m[1],
    )));
}

it('renders every public page of the real content in both languages without placeholders, remote images or broken markup', function () {
    $this->artisan('sakila:load-content')->assertSuccessful();

    $paths = sitemapPaths((string) $this->get('/sitemap.xml')->assertOk()->getContent());
    // Pages that exist but are deliberately not in the sitemap.
    $paths = [...$paths, '/it/donate/cancelled', '/en/donate/cancelled', '/it/newsletter', '/en/newsletter'];

    expect(count($paths))->toBeGreaterThanOrEqual(44)
        ->and(array_filter($paths, fn (string $p): bool => str_starts_with($p, '/it')))->not->toBeEmpty()
        ->and(array_filter($paths, fn (string $p): bool => str_starts_with($p, '/en')))->not->toBeEmpty();

    $ownHost = (string) parse_url((string) config('sakila.site_url'), PHP_URL_HOST);
    foreach ($paths as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        foreach (['[[TODO' => 'placeholder text', 'Sakila Admin' => 'admin account name', 'Content loader' => 'loader account name', '__contextArgs' => 'template code printed into the page'] as $needle => $what) {
            expect(str_contains($html, $needle))->toBeFalse("{$path}: {$what}");
        }

        expect(preg_match_all('#<h1[\s>]#', $html))->toBe(1, "{$path}: exactly one <h1>");

        // Images: every one described (alt attribute present) and none loaded from another host.
        preg_match_all('#<img\b[^>]*>#', $html, $imgs);
        foreach ($imgs[0] as $img) {
            expect(str_contains($img, ' alt='))->toBeTrue("{$path}: <img> without alt: {$img}");
            preg_match_all('#\b(?:src|srcset)="([^"]*)"#', $img, $srcs);
            foreach ($srcs[1] as $src) {
                foreach (preg_split('/\s*,\s*/', html_entity_decode($src)) ?: [] as $candidate) {
                    $url = (string) strtok(trim($candidate), ' ');
                    $host = (string) parse_url($url, PHP_URL_HOST);
                    expect($host === '' || $host === $ownHost || $host === 'localhost' || $host === '127.0.0.1')
                        ->toBeTrue("{$path}: image from another host: {$url}");
                }
            }
        }

        // Structured data: every block is valid JSON and declares its vocabulary.
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
        expect($blocks[1])->not->toBeEmpty("{$path}: no structured data");
        foreach ($blocks[1] as $json) {
            $data = json_decode($json, true);
            expect($data)->toBeArray("{$path}: structured data is not valid JSON")
                ->and($data['@context'] ?? null)->toBe('https://schema.org', "{$path}: structured data without @context");
        }
    }
});

it('shows the real news dates, the planned project without a call for donations, and one newsletter form on the home page', function () {
    $this->artisan('sakila:load-content')->assertSuccessful();

    // News: the date of the fact, in the page, in the metadata and in the structured data.
    $flood = (string) $this->get('/it/news/alluvione-in-nepal-raccolta-fondi')->assertOk()->getContent();
    expect($flood)->toContain('26 agosto 2026')
        ->and($flood)->toContain('2026-08-26T')
        ->and($flood)->not->toContain(now()->format('Y-m-d').'T');
    $recap = (string) $this->get('/en/news/play-for-nepal-first-edition')->assertOk()->getContent();
    expect($recap)->toContain('2026-05-16T');

    // The event page carries, as text, what the posters only show as pictures.
    $event = (string) $this->get('/it/events/play-for-nepal-2026')->assertOk()->getContent();
    expect($event)->toContain('Girone A: Barianese, Vailate, Zanconti, Virtus Adda')
        ->and($event)->toContain('Viale Piave 26')
        ->and($event)->toContain('La seconda edizione di Play for Nepal è già in programma');

    // The project that is only planned never asks for money: no donate link for it, on its own
    // page or in the list, and no fundraising goal.
    $planned = (string) $this->get('/it/projects/cooperativa-agricola-di-donne')->assertOk()->getContent();
    $list = (string) $this->get('/it/projects')->assertOk()->getContent();
    expect($planned)->toContain('In programma')
        ->and($planned)->not->toContain('project=cooperativa-agricola-di-donne')
        ->and($list)->not->toContain('project=cooperativa-agricola-di-donne')
        ->and($list)->toContain('project=lavoro-per-donne-e-famiglie');

    // The fundraiser flyer (image of text, unconfirmed photo) is not published anywhere.
    $album = (string) $this->get('/it/gallery/aiuti-alluvione-2026')->assertOk()->getContent();
    expect($album)->not->toContain('volantino');

    // The home page does not stack a newsletter block on top of the footer's sign-up form.
    $home = (string) $this->get('/it')->assertOk()->getContent();
    expect(substr_count($home, 'data-engagement="newsletter-form"'))->toBe(1);

    // Search descriptions: present and unique per language.
    $seen = [];
    foreach (sitemapPaths((string) $this->get('/sitemap.xml')->getContent()) as $path) {
        $html = (string) $this->get($path)->getContent();
        expect(preg_match('#<meta name="description" content="([^"]*)"#', $html, $m))->toBe(1, "{$path}: no meta description");
        $key = substr($path, 0, 3).'|'.$m[1];
        expect($seen[$key] ?? null)->toBeNull("{$path} has the same description as ".($seen[$key] ?? ''));
        $seen[$key] = $path;
    }
});

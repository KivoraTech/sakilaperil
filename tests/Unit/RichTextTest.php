<?php

declare(strict_types=1);

use App\RichText\RichText;
use App\RichText\Video;

/*
| Port of sakila/tests/unit/richtext.test.ts (sanitizer allow-list, safe renderer, helpers).
| Expected HTML strings are what React's renderToStaticMarkup produces for <RichText> in the reference.
*/

function rt_p(mixed ...$content): array
{
    return ['type' => 'paragraph', 'content' => $content];
}

function rt_t(string $text, ?array $marks = null): array
{
    return ['type' => 'text', 'text' => $text] + ($marks !== null ? ['marks' => $marks] : []);
}

function rt_link(string $href): array
{
    return ['type' => 'link', 'attrs' => ['href' => $href]];
}

describe('sanitize (allow-list)', function (): void {
    it('keeps allowed structure and marks', function (): void {
        $doc = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [rt_t('Titolo')]],
                rt_p(rt_t('bold', [['type' => 'bold']]), rt_t(' and '), rt_t('link', [rt_link('https://example.org')])),
                ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [rt_p(rt_t('one'))]]]],
                ['type' => 'blockquote', 'content' => [rt_p(rt_t('quote'))]],
            ],
        ];
        expect(RichText::sanitize($doc))->toBe($doc);
    });

    it('drops unknown nodes, marks and attributes', function (): void {
        $out = RichText::sanitize([
            'type' => 'doc',
            'content' => [
                ['type' => 'script', 'content' => [rt_t('alert(1)')]],
                ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.example']],
                rt_p(rt_t('x', [['type' => 'code'], ['type' => 'bold', 'attrs' => ['onclick' => 'alert(1)']]])),
                ['type' => 'paragraph', 'attrs' => ['style' => 'color:red', 'onmouseover' => 'x'], 'content' => [rt_t('y')]],
            ],
        ]);
        expect($out)->toBe(['type' => 'doc', 'content' => [rt_p(rt_t('x', [['type' => 'bold']])), rt_p(rt_t('y'))]]);
    });

    it('removes unsafe link hrefs but keeps the text', function (string $href): void {
        $out = RichText::sanitize(['type' => 'doc', 'content' => [rt_p(rt_t('click', [rt_link($href)]))]]);
        expect($out['content'][0])->toBe(rt_p(rt_t('click')));
    })->with([
        'javascript:alert(1)',
        'JaVaScRiPt:alert(1)',
        ' javascript:alert(1)',
        'data:text/html,<script>alert(1)</script>',
        'vbscript:msgbox(1)',
        '//evil.example',
        '/\\evil.example',
        'http://insecure.example',
        "/ok\njavascript:alert(1)",
    ]);

    it('allows only valid video embeds and safe image sources', function (): void {
        $out = RichText::sanitize([
            'type' => 'doc',
            'content' => [
                ['type' => 'videoEmbed', 'attrs' => ['provider' => 'youtube', 'videoId' => 'dQw4w9WgXcQ']],
                ['type' => 'videoEmbed', 'attrs' => ['provider' => 'youtube', 'videoId' => '"><script>']],
                ['type' => 'videoEmbed', 'attrs' => ['provider' => 'evil', 'videoId' => '123']],
                ['type' => 'image', 'attrs' => ['src' => '/uploads/2026/09/abc.jpg', 'alt' => 'A', 'assetId' => 'a1']],
                ['type' => 'image', 'attrs' => ['src' => 'javascript:alert(1)']],
                ['type' => 'image', 'attrs' => ['src' => 'data:image/png;base64,AAAA']],
                ['type' => 'image', 'attrs' => ['src' => '/uploads/x.jpg" onerror="alert(1)']],
            ],
        ]);
        expect(array_column($out['content'], 'type'))->toBe(['videoEmbed', 'image']);
    });

    it('clamps heading levels and survives garbage input', function (): void {
        $out = RichText::sanitize(['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [rt_t('h')]]]]);
        expect($out['content'][0]['attrs'])->toBe(['level' => 2]);
        expect(RichText::sanitize(['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 6], 'content' => []]]])['content'][0]['attrs'])->toBe(['level' => 4]);
        expect(RichText::sanitize(null))->toBe(['type' => 'doc', 'content' => []]);
        expect(RichText::sanitize('<script>'))->toBe(['type' => 'doc', 'content' => []]);
        expect(RichText::sanitize(['type' => 'doc', 'content' => 'nope']))->toBe(['type' => 'doc', 'content' => []]);
    });

    it('bounds nesting depth', function (): void {
        $deep = rt_p(rt_t('deep'));
        for ($i = 0; $i < 50; $i++) {
            $deep = ['type' => 'blockquote', 'content' => [$deep]];
        }
        expect(json_encode(RichText::sanitize(['type' => 'doc', 'content' => [$deep]])))->not->toContain('deep');
    });
});

describe('render', function (): void {
    it('never outputs script tags, event handlers or javascript: URLs', function (): void {
        $html = RichText::render([
            'type' => 'doc',
            'content' => [
                ['type' => 'script', 'content' => [rt_t('alert(1)')]],
                rt_p(rt_t('<script>alert(1)</script>')),
                rt_p(rt_t('x', [rt_link('javascript:alert(1)')])),
                ['type' => 'image', 'attrs' => ['src' => '/uploads/a.jpg', 'alt' => '" onerror="alert(1)']],
                ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)'], 'content' => [rt_t('y')]],
            ],
        ]);
        expect($html)->not->toMatch('/<script/i')
            ->and($html)->not->toMatch('/javascript:/i')
            ->and($html)->not->toMatch('/\sonerror="/i')
            ->and($html)->toContain('alt="&quot; onerror=&quot;alert(1)"')
            ->and($html)->not->toMatch('/\sonclick=/i')
            ->and($html)->toContain('&lt;script&gt;');
    });

    it('adds rel to external links and locale-prefixes internal ones', function (): void {
        $html = RichText::render(['type' => 'doc', 'content' => [rt_p(
            rt_t('ext', [rt_link('https://example.org')]),
            rt_t('int', [rt_link('/projects')]),
            rt_t('pdf', [rt_link('/uploads/2026/09/r.pdf')]),
            rt_t('mail', [rt_link('mailto:info@example.org')]),
        )]], 'en');
        expect($html)->toContain('<a href="https://example.org" rel="noopener noreferrer nofollow">ext</a>')
            ->and($html)->toContain('<a href="/en/projects">int</a>')
            ->and($html)->toContain('<a href="/uploads/2026/09/r.pdf">pdf</a>')
            ->and($html)->toContain('href="mailto:info@example.org"');
    });

    it('renders video embeds only via our own embed URL', function (): void {
        $html = RichText::render(['type' => 'doc', 'content' => [['type' => 'videoEmbed', 'attrs' => ['provider' => 'vimeo', 'videoId' => '76979871']]]]);
        expect($html)->toBe(
            '<div><div class="rt-video" style="position:relative;aspect-ratio:16 / 9"><iframe src="https://player.vimeo.com/video/76979871?dnt=1" title="Vimeo video" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowFullScreen="" referrerPolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe></div></div>'
        );
    });

    it('renders nothing for empty docs', function (): void {
        expect(RichText::render(['type' => 'doc', 'content' => []]))->toBe('');
    });

    it('matches the reference markup for every node type', function (): void {
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [rt_t('H')]],
            rt_p(rt_t('a', [['type' => 'bold'], ['type' => 'italic'], rt_link('/chi-siamo#team')]), ['type' => 'hardBreak'], rt_t("it's")),
            ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [['type' => 'listItem', 'content' => [rt_p(rt_t('x'))]]]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1], 'content' => []],
            ['type' => 'horizontalRule'],
            ['type' => 'image', 'attrs' => ['src' => '/uploads/a.jpg', 'alt' => 'old', 'assetId' => 'a1', 'caption' => 'Cap']],
            ['type' => 'image', 'attrs' => ['src' => 'https://cdn.example/b.jpg', 'width' => 10]],
        ]];
        $assets = ['a1' => ['url' => '/uploads/new.jpg', 'width' => 800, 'height' => 600, 'alt' => 'Nuovo']];
        expect(RichText::render($doc, 'it', $assets, 'prose-sakila'))->toBe(
            '<div class="prose-sakila"><h3>H</h3><p><a href="/it/chi-siamo#team"><em><strong>a</strong></em></a><br/>it&#x27;s</p>'
            .'<ol start="3"><li><p>x</p></li></ol><ol></ol><hr/>'
            .'<figure><img src="/uploads/new.jpg" alt="Nuovo" width="800" height="600" loading="lazy" decoding="async"/><figcaption>Cap</figcaption></figure>'
            .'<figure><img src="https://cdn.example/b.jpg" alt="" width="10" loading="lazy" decoding="async"/></figure></div>'
        );
    });
});

describe('helpers', function (): void {
    it('parses YouTube and Vimeo URLs and rejects others', function (): void {
        expect(Video::parseUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'))->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'])
            ->and(Video::parseUrl('https://youtu.be/dQw4w9WgXcQ')['id'] ?? null)->toBe('dQw4w9WgXcQ')
            ->and(Video::parseUrl('https://www.youtube.com/shorts/dQw4w9WgXcQ')['id'] ?? null)->toBe('dQw4w9WgXcQ')
            ->and(Video::parseUrl('https://vimeo.com/76979871'))->toBe(['provider' => 'vimeo', 'id' => '76979871'])
            ->and(Video::parseUrl('https://player.vimeo.com/video/76979871')['id'] ?? null)->toBe('76979871')
            ->and(Video::parseUrl('https://evil.example/watch?v=dQw4w9WgXcQ'))->toBeNull()
            ->and(Video::parseUrl('javascript:alert(1)'))->toBeNull()
            ->and(Video::parseUrl('https://www.youtube.com/watch?v=short'))->toBeNull();
        expect(fn () => Video::embedUrl('youtube', 'bad id'))->toThrow(InvalidArgumentException::class);
    });

    it('extracts plain text and asset ids', function (): void {
        $doc = ['type' => 'doc', 'content' => [
            rt_p(rt_t('Ciao '), rt_t('mondo')),
            rt_p(rt_t('due')),
            ['type' => 'image', 'attrs' => ['src' => '/uploads/a.jpg', 'assetId' => 'a1']],
        ]];
        expect(RichText::toPlainText($doc))->toBe('Ciao mondo due')
            ->and(RichText::collectAssetIds($doc))->toBe(['a1'])
            ->and(RichText::isEmpty(['type' => 'doc', 'content' => [rt_p()]]))->toBeTrue()
            ->and(RichText::isEmpty($doc))->toBeFalse();
    });
});

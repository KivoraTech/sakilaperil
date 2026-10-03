<?php

declare(strict_types=1);

use App\View\Html;
use App\View\Img;
use App\View\JsxWhitespace;
use App\View\Seo;
use App\View\Ui;

it('applies JSX whitespace rules to template source', function () {
    expect(JsxWhitespace::compact("<p>\n  <span>a</span>\n  text\n  more\n</p>"))->toBe('<p><span>a</span>text more</p>')
        ->and(JsxWhitespace::compact('<b>x</b> <i>y</i>'))->toBe('<b>x</b> <i>y</i>')
        ->and(JsxWhitespace::compact("word\n@if(\$a)"))->toBe('word<?php ?>@if($a)');
});

it('computes next/image widths like Next', function () {
    expect(Img::widths('100vw'))->toBe([640, 828, 1080, 1440, 1920])
        ->and(Img::widths('(min-width: 1024px) 20vw, 45vw'))->toBe([128, 256, 384, 640, 828, 1080, 1440, 1920])
        ->and(Img::widths('44px'))->toBe([64, 128, 256, 384, 640, 828, 1080, 1440, 1920]);
});

it('builds hreflang alternates and canonical like the reference', function () {
    $a = Seo::languageAlternates('en', ['it' => '/chi-siamo']);
    expect($a['canonical'])->toBe('/it/chi-siamo')
        ->and($a['languages'])->toBe(['it' => '/it/chi-siamo', 'x-default' => '/it/chi-siamo']);
});

it('serialises attributes like React', function () {
    expect((string) Html::attrs(['data-reveal' => true, 'aria-hidden' => true, 'hidden' => false, 'style' => ['--i' => 2]]))
        ->toBe(' data-reveal="true" aria-hidden="true" style="--i:2"');
});

it('rejects unsafe hrefs', function (string $href, bool $ok) {
    expect(preg_match(Ui::SAFE_HREF, $href) === 1)->toBe($ok);
})->with([['/projects', true], ['//evil.com', false], ['/\\evil.com', false], ['javascript:alert(1)', false], ['https://x.y', true], ['#dona', true]]);

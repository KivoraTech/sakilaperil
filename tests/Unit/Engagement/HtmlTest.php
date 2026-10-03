<?php

declare(strict_types=1);

use App\Engagement\View\Html;

/* Html::cx must agree with `cn` (clsx + tailwind-merge) for the class combinations the components use. */

it('merges classes like cn', function (array $parts, string $expected) {
    expect(Html::cx(...$parts))->toBe($expected);
})->with([
    'plain join' => [['relative', 'mt-6'], 'relative mt-6'],
    'falsy dropped' => [['relative', null, false, ''], 'relative'],
    'exact duplicate keeps last' => [['a b', 'a'], 'b a'],
    'cursor conflict' => [['flex cursor-pointer gap-1', 'cursor-not-allowed opacity-50'], 'flex gap-1 cursor-not-allowed opacity-50'],
    'bg colour conflict' => [['border bg-white px-4', 'bg-paper'], 'border px-4 bg-paper'],
    'variant-scoped outline colour' => [[Html::INPUT, 'sm:flex-1', 'focus-visible:outline-[#F6C77A]'], str_replace(' focus-visible:outline-focus', '', Html::INPUT).' sm:flex-1 focus-visible:outline-[#F6C77A]'],
    'outline width/offset are separate groups' => [['focus-visible:outline-2 focus-visible:outline-offset-2', 'focus-visible:outline-focus'], 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus'],
    'has-[:checked] bg is its own variant' => [['bg-white has-[:checked]:bg-sindoor-50', 'bg-paper'], 'has-[:checked]:bg-sindoor-50 bg-paper'],
]);

it('escapes like React', function () {
    expect(Html::e("L'a & <b> \"x\""))->toBe('L&#x27;a &amp; &lt;b&gt; &quot;x&quot;')
        ->and(Html::attrs(['a' => true, 'b' => false, 'c' => null, 'd' => 0, 'e' => 'x"y']))->toBe(' a="" d="0" e="x&quot;y"');
});

it('renders lucide icons exactly like lucide-react', function () {
    expect(Html::icon('AlertTriangle', 'mt-0.5 size-5 shrink-0'))
        ->toBe('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert lucide-alert-triangle mt-0.5 size-5 shrink-0" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>');
});

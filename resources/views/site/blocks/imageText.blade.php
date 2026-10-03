{{--
    components/site/blocks/ImageText.tsx: text beside one photo or a two-photo collage with a round
    "stamp" badge. Long headings (> 110 chars) use the statement style, lit word by word.
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($main = $context['assets'][$c['image']['assetId']] ?? null)
@php($second = ! empty($c['secondaryImage']) ? ($context['assets'][$c['secondaryImage']['assetId']] ?? null) : null)
@php($statement = mb_strlen($c['heading']) > 110)
@php($imageLeft = $s['imagePosition'] === 'left')
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="imageText">
<x-site.container class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">
<div class="{{ \App\View\Html::cx('lg:col-span-6', $imageLeft ? 'lg:order-2' : '') }}">
@if ($statement)
<div data-reveal="true" data-statement="true">
@if (! empty($c['kicker']))
<p class="kicker">{{ $c['kicker'] }}</p>
@endif
<h2 class="mt-5 font-display text-statement font-normal text-heading">@partial('site.motion.statement-words', ['text' => $c['heading'], 'accent' => $c['headingAccent'] ?? null])</h2>
</div>
@else
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'], 'accent' => $c['headingAccent'] ?? null])
@endif
@if (! empty($c['body']))
<div class="mt-6 space-y-4 text-lead text-fg-muted" data-reveal="true">
@foreach (preg_split('/\n{2,}/', $c['body']) as $p)
<p>{{ \App\View\Ui::todoText($p) }}</p>
@endforeach
</div>
@endif
@if (! empty($c['primaryCta']) || ! empty($c['secondaryCta']))
<div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3" data-reveal="true">
@if (! empty($c['primaryCta']))
{{ \App\View\Ui::buttonLink($c['primaryCta']['href'], $c['primaryCta']['label'], variant: 'secondary', arrow: true) }}
@endif
@if (! empty($c['secondaryCta']))
{{ \App\View\Ui::buttonLink($c['secondaryCta']['href'], $c['secondaryCta']['label'], variant: 'quiet', arrow: true) }}
@endif
</div>
@endif
</div>
<div class="{{ \App\View\Html::cx('relative lg:col-span-6', $imageLeft ? 'lg:order-1' : '') }}">
<div class="{{ \App\View\Html::cx('relative', $second ? 'pr-[18%] pb-[16%]' : '') }}">
<div class="relative aspect-[4/5] overflow-hidden rounded-lg bg-sand shadow-raised sm:aspect-[5/4] lg:aspect-[4/5]" data-reveal="wipe-up">
@partial('site.motion.parallax-open', ['speed' => 8])
{{ \App\View\Img::media($main, sizes: '(min-width: 1024px) 40vw, 100vw', alt: $c['image']['alt'] ?? null) }}
</div>
</div>
@if ($second)
<div class="absolute right-0 bottom-0 aspect-square w-[46%] overflow-hidden rounded-lg bg-sand shadow-panel ring-[6px] ring-bg" data-reveal="wipe-left" style="--reveal-i:3">
@partial('site.motion.parallax-open', ['speed' => 10])
{{ \App\View\Img::media($second, sizes: '(min-width: 1024px) 20vw, 45vw', alt: $c['secondaryImage']['alt'] ?? null) }}
</div>
</div>
@endif
</div>
@if (! empty($c['badgeText']))
<div class="absolute top-[-1.25rem] left-[-0.5rem] flex size-28 items-center justify-center rounded-full bg-marigold p-3 text-center shadow-raised sm:size-32 lg:left-[-1.5rem]" aria-hidden="true" data-reveal="stamp">
<svg viewBox="0 0 100 100" class="absolute inset-0 size-full"><defs><path id="badge-{{ $block['id'] }}" d="M50 50 m-38 0 a38 38 0 1 1 76 0 a38 38 0 1 1 -76 0"></path></defs><text class="fill-deep text-[9.5px] font-semibold tracking-[0.18em] uppercase"><textPath href="#badge-{{ $block['id'] }}" textLength="236" lengthAdjust="spacing">{{ $c['badgeText'] }} · </textPath></text></svg>
<svg viewBox="0 0 48 48" class="size-9"><path d="M5 34 L16 20 L22 27 L29 17 L43 34 Z" fill="#101F33"></path><circle cx="31" cy="13" r="4" fill="#FBF7F0"></circle></svg>
</div>
@endif
@if (! empty($c['badgeText']))
<p class="sr-only">{{ $c['badgeText'] }}</p>
@endif
</div>
</x-site.container>
</x-site.section>

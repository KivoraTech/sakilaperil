{{--
    components/site/blocks/Quote.tsx. `band`: full-bleed photo statement on a deep scrim (parallax).
    `story`: beneficiary story on the himalaya surface (photo wipe + quote + name/role + CTA).
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($image = ! empty($c['image']) ? ($context['assets'][$c['image']['assetId']] ?? null) : null)
@php($hasAuthor = ! empty($c['author']))
@php($quoteText = e($c['quote']).(! empty($c['quoteAccent']) ? ' <em class="heading-accent">'.e($c['quoteAccent']).'</em>' : ''))
@if ($s['variant'] === 'band')
<section{{ \App\View\Html::attrs(['id' => $s['anchor'] ?? null, 'data-block' => 'quote', 'class' => 'surface-dark relative isolate overflow-hidden']) }}>
@if ($image)
<div class="absolute inset-0 -z-10 overflow-hidden">
@partial('site.motion.parallax-open', ['speed' => 12])
{{ \App\View\Img::media($image, sizes: '100vw', alt: $c['image']['alt'] ?? '', class: 'brightness-90 saturate-90') }}
</div>
</div>
@endif
<div class="absolute inset-0 -z-10 bg-deep/70" aria-hidden="true"></div>
<div class="absolute inset-0 -z-10 bg-gradient-to-t from-deep/80 via-transparent to-deep/40" aria-hidden="true"></div>
<x-site.container size="content" class="flex min-h-[26rem] flex-col items-center justify-center py-section text-center md:min-h-[32rem]">
@if (! empty($c['kicker']))
<p class="kicker justify-center">{{ $c['kicker'] }}</p>
@endif
@if ($hasAuthor)
<figure data-reveal="true">
<blockquote class="font-display text-h2 font-medium text-white"><p>{!! $quoteText !!}</p></blockquote>
<figcaption class="mt-6 text-small text-mist">{{ $c['author'] }}{{ ! empty($c['role']) ? ', '.$c['role'] : '' }}</figcaption>
</figure>
@else
<h2 class="font-display text-h1 font-medium text-white" data-reveal="true">{!! $quoteText !!}</h2>
@endif
@if (! empty($c['body']))
<p class="mt-6 max-w-xl text-kicker font-semibold tracking-[0.2em] text-gold uppercase" data-reveal="true">{{ $c['body'] }}</p>
@endif
@if (! empty($c['cta']))
<div class="mt-8" data-reveal="true" style="--reveal-i:2">{{ \App\View\Ui::buttonLink($c['cta']['href'], $c['cta']['label'], variant: 'ghost-light', arrow: true) }}</div>
@endif
</x-site.container>
</section>
@else
<x-site.section :id="$s['anchor'] ?? null" surface="himalaya" block="quote">
<x-site.container class="grid items-center gap-10 lg:grid-cols-12 lg:gap-16">
@if ($image)
<figure class="lg:col-span-5">
<div class="relative aspect-[4/5] overflow-hidden rounded-lg outline outline-1 outline-white/10" data-reveal="wipe-up">
@partial('site.motion.parallax-open', ['speed' => 8])
{{ \App\View\Img::media($image, sizes: '(min-width: 1024px) 34vw, 100vw', alt: $c['image']['alt'] ?? null) }}
</div>
</div>
@if (! empty($c['imageCaption']))
<figcaption class="mt-3 text-small text-fg-muted">{{ $c['imageCaption'] }}</figcaption>
@endif
</figure>
@endif
<div class="{{ $image ? 'lg:col-span-7' : 'lg:col-span-10 lg:col-start-2' }}">
@if (! empty($c['kicker']))
<p class="kicker">{{ $c['kicker'] }}</p>
@endif
<figure class="mt-6" data-reveal="true">
{!! \App\View\Icon::svg('Quote', 'size-10 text-marigold') !!}
<blockquote class="mt-4 font-display text-statement font-normal text-white italic"><p>“{{ $c['quote'] }}{{ ! empty($c['quoteAccent']) ? ' '.$c['quoteAccent'] : '' }}”</p></blockquote>
@if ($hasAuthor)
<figcaption class="mt-6 flex items-center gap-4">
<span class="flex size-12 items-center justify-center rounded-full bg-marigold font-display text-xl font-semibold text-deep" aria-hidden="true">{{ mb_substr($c['author'], 0, 1) }}</span>
<span>
<span class="block font-semibold text-white">{{ $c['author'] }}</span>
@if (! empty($c['role']))
<span class="block text-small text-fg-muted">{{ $c['role'] }}</span>
@endif
</span>
</figcaption>
@endif
</figure>
@if (! empty($c['body']))
<p class="mt-8 max-w-xl border-l-2 border-marigold pl-5 text-fg-muted" data-reveal="true">{{ \App\View\Ui::todoText($c['body']) }}</p>
@endif
@if (! empty($c['cta']))
<div class="mt-8" data-reveal="true">{{ \App\View\Ui::buttonLink($c['cta']['href'], $c['cta']['label'], arrow: true) }}</div>
@endif
</div>
</x-site.container>
</x-site.section>
@endif

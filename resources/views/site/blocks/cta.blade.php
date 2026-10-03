{{-- components/site/blocks/Cta.tsx: call-to-action card. Marigold = the 5×1000 panel with deep text. --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($image = ! empty($c['image']) ? ($context['assets'][$c['image']['assetId']] ?? null) : null)
@php($dark = $s['tone'] === 'himalaya' || $s['tone'] === 'sindoor')
@php($toneSurface = ['sindoor' => 'surface-dark bg-sindoor! [--color-accent-text:var(--color-white)] [--color-fg-muted:var(--color-white)] [--color-fg:var(--color-white)]', 'himalaya' => \App\View\Ui::SURFACE_CLASS['himalaya'], 'marigold' => \App\View\Ui::SURFACE_CLASS['marigold'], 'paper' => 'surface-white ring-1 ring-line'])
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" spacing="compact" block="cta">
<x-site.container>
<div class="{{ \App\View\Html::cx('relative isolate grid overflow-hidden rounded-xl md:grid-cols-12', $toneSurface[$s['tone']], $s['tone'] !== 'paper' ? 'shadow-panel' : null) }}" data-reveal="true">
@if ($s['tone'] === 'marigold')
<svg aria-hidden="true" viewBox="0 0 400 200" class="absolute right-0 bottom-0 -z-10 w-[26rem] max-w-[70%] text-deep/10" fill="currentColor"><path d="M0 200 L90 90 L140 140 L220 40 L300 150 L340 110 L400 170 L400 200 Z"></path></svg>
@endif
<div class="{{ \App\View\Html::cx('p-8 md:p-12 lg:p-14', $image ? 'md:col-span-7' : 'md:col-span-12 md:flex md:items-center md:justify-between md:gap-10') }}">
<div class="max-w-2xl">
@if (! empty($c['kicker']))
@if ($s['tone'] === 'marigold')
<p class="font-display text-[3.5rem] leading-none font-semibold tracking-tight text-deep">{{ $c['kicker'] }}</p>
@else
<p class="kicker">{{ $c['kicker'] }}</p>
@endif
@endif
<h2 class="{{ \App\View\Html::cx('mt-4 font-display text-h2 font-semibold text-heading', $s['tone'] === 'marigold' ? 'text-h3!' : null) }}">{{ $c['heading'] }}@if (! empty($c['headingAccent'])) <em class="heading-accent">{{ $c['headingAccent'] }}</em>@endif</h2>
@if (! empty($c['body']))
<p class="mt-4 text-lead text-fg-muted">{{ \App\View\Ui::todoText($c['body']) }}</p>
@endif
</div>
<div class="mt-8 flex shrink-0 flex-wrap gap-3 md:mt-0">
{{ \App\View\Ui::buttonLink($c['primaryCta']['href'], $c['primaryCta']['label'], variant: $s['tone'] === 'sindoor' ? 'light' : ($s['tone'] === 'marigold' ? 'secondary' : 'primary'), arrow: true, class: $image ? 'md:mt-8' : '') }}
@if (! empty($c['secondaryCta']))
{{ \App\View\Ui::buttonLink($c['secondaryCta']['href'], $c['secondaryCta']['label'], variant: $dark ? 'ghost-light' : 'secondary', class: $image ? 'md:mt-8' : '') }}
@endif
</div>
</div>
@if ($image)
<div class="relative min-h-60 md:col-span-5">{{ \App\View\Img::media($image, sizes: '(min-width: 768px) 40vw, 100vw', alt: $c['image']['alt'] ?? null) }}</div>
@endif
</div>
</x-site.container>
</x-site.section>

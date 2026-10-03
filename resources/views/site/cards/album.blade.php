{{-- components/site/cards/album-card.tsx. Params: $album, $locale, $priority, $headingLevel ('h3'; 'h2' when the cards follow the page h1). --}}
@php($acH = $headingLevel ?? 'h3')
<article class="group relative">
<div class="relative aspect-[4/3] overflow-hidden rounded-lg bg-sand shadow-card">
{{ \App\View\Img::media($album['coverImage'], sizes: '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw', alt: '', priority: ! empty($priority), class: 'transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.06]') }}
<div class="absolute inset-0 bg-gradient-to-t from-deep/70 via-deep/0 to-deep/0" aria-hidden="true"></div>
<span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1 text-[0.8125rem] font-semibold text-himalaya">{!! \App\View\Icon::svg('Images', 'size-4') !!}{{ t('gallery.count', ['count' => $album['itemCount']]) }}</span>
</div>
<{{ $acH }} class="mt-4 font-display text-h3 font-semibold text-heading"><a class="stretched-link link-sweep" href="{{ lurl(\App\I18n\Routes::album($album['slug'])) }}">{{ $album['title'] }}</a></{{ $acH }}>
@if (! empty($album['takenAt']))
<p class="mt-1 text-small text-fg-muted"><time datetime="{{ $album['takenAt'] }}">{{ \App\I18n\Format::date($album['takenAt'], $locale, ['month' => 'long', 'year' => 'numeric']) }}</time></p>
@endif
</article>

{{--
    components/site/cards/news-card.tsx: `feature` = large horizontal, `compact` = thumbnail + title.
    Params: $post, $locale, $variant ('grid'|'feature'|'compact'), $headingLevel ('h3'), $class.
--}}
@php($ncVariant = $variant ?? 'grid')
@php($ncH = $headingLevel ?? 'h3')
@php($ncHref = lurl(\App\I18n\Routes::news($post['slug'])))
@php($ncDate = ! empty($post['publishedAt']) ? '<time datetime="'.e($post['publishedAt']).'" class="text-kicker font-semibold tracking-[0.12em] text-accent-text uppercase">'.e(\App\I18n\Format::date($post['publishedAt'], $locale)).'</time>' : '')
@if ($ncVariant === 'compact')
<article class="{{ \App\View\Html::cx('group relative flex gap-4 rounded-md p-2 hover:bg-cream', $class ?? null) }}">
<div class="relative size-22 shrink-0 overflow-hidden rounded-md bg-sand">{{ \App\View\Img::media($post['coverImage'], sizes: '88px', alt: '') }}</div>
<div class="min-w-0">{!! $ncDate !!}<{{ $ncH }} class="mt-1 line-clamp-3 font-display text-lg leading-snug font-semibold text-heading"><a class="stretched-link" href="{{ $ncHref }}">{{ $post['title'] }}</a></{{ $ncH }}></div>
</article>
@else
@php($ncFeature = $ncVariant === 'feature')
<article class="{{ \App\View\Html::cx('group relative flex flex-col', $ncFeature ? 'overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-line/70 md:grid md:grid-cols-12' : null, $class ?? null) }}">
<div class="{{ \App\View\Html::cx('relative overflow-hidden bg-sand', $ncFeature ? 'aspect-[4/3] md:col-span-7 md:aspect-auto md:min-h-[26rem]' : 'aspect-[4/3] rounded-lg') }}">
{{ \App\View\Img::media($post['coverImage'], sizes: $ncFeature ? '(min-width: 768px) 58vw, 100vw' : '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw', class: 'transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.06]', containPortrait: true) }}
@if (! empty($post['category']))
<div class="absolute top-3 left-3">@partial('site.ui.chip', ['tone' => 'white', 'slot' => $post['category']['name']])</div>
@endif
<span class="card-badge absolute right-3 bottom-3 flex size-11 items-center justify-center rounded-full bg-marigold text-deep shadow-raised" aria-hidden="true">{!! \App\View\Icon::svg('ArrowUpRight', 'size-5') !!}</span>
</div>
<div class="{{ $ncFeature ? 'flex flex-col justify-center p-6 md:col-span-5 md:p-10' : 'pt-5' }}">
{!! $ncDate !!}
<{{ $ncH }} class="{{ \App\View\Html::cx('mt-2 font-display font-semibold text-heading', $ncFeature ? 'text-h2' : 'line-clamp-3 text-h3') }}"><a class="stretched-link link-sweep" href="{{ $ncHref }}">{{ $post['title'] }}</a></{{ $ncH }}>
@if (! empty($post['excerpt']))
<p class="{{ \App\View\Html::cx('mt-3 text-fg-muted', $ncFeature ? 'line-clamp-4 text-lead' : 'line-clamp-2') }}">{{ \App\View\Ui::todoText($post['excerpt']) }}</p>
@endif
@if ($ncFeature)
<p class="mt-6 font-semibold text-accent-text" aria-hidden="true">{{ t('cta.readMore') }} →</p>
@endif
</div>
</article>
@endif

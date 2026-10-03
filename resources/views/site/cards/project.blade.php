{{--
    components/site/cards/project-card.tsx: one stretched link, optional progress bar, "support" link on top.
    Params: $project, $locale, $variant ('grid'|'feature'), $headingLevel ('h3'), $priority, $class.
--}}
@php($pcFeature = ($variant ?? 'grid') === 'feature')
@php($pcH = $headingLevel ?? 'h3')
@php($pcStage = \App\View\Cards::STAGE_TONE[$project['stage']])
<article class="{{ \App\View\Html::cx('group relative flex overflow-hidden rounded-lg bg-white shadow-card ring-1 ring-line/70 transition-[box-shadow,transform,translate] duration-300 ease-out-soft hover:shadow-raised motion:hover:-translate-y-1', $pcFeature ? 'flex-col md:grid md:grid-cols-12' : 'flex-col', $class ?? null) }}">
@php($pcHasCover = ! empty($project['coverImage']))
@if ($pcHasCover)
<div class="{{ \App\View\Html::cx('relative overflow-hidden bg-sand', $pcFeature ? 'aspect-[3/2] md:col-span-7 md:aspect-auto md:min-h-96' : 'aspect-[3/2]') }}">
{{ \App\View\Img::media($project['coverImage'], sizes: $pcFeature ? '(min-width: 768px) 58vw, 100vw' : '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw', priority: ! empty($priority), class: 'transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.06]') }}
<div class="absolute inset-x-3 top-3 flex flex-wrap items-start justify-between gap-2">
@if (! empty($project['sector']))
@partial('site.ui.chip', ['tone' => 'white', 'slot' => $project['sector']])
@else
<span></span>
@endif
@partial('site.ui.chip', ['tone' => 'white', 'dot' => $pcStage['dot'], 'slot' => t('status.'.$pcStage['key'])])
</div>
</div>
@endif
<div class="{{ \App\View\Html::cx('flex flex-1 flex-col p-6', $pcFeature ? ($pcHasCover ? 'md:col-span-5 md:justify-center md:p-10' : 'md:col-span-12 md:p-10') : null) }}">
@if (! $pcHasCover)
{{-- No photo yet (e.g. a planned project): a text-only card, the chips move into the body. --}}
<div class="mb-4 flex flex-wrap gap-2">
@if (! empty($project['sector']))
@partial('site.ui.chip', ['slot' => $project['sector']])
@endif
@partial('site.ui.chip', ['tone' => 'sage', 'dot' => $pcStage['dot'], 'slot' => t('status.'.$pcStage['key'])])
</div>
@endif
@if (! empty($project['district']) || ! empty($project['metaLine']))
<p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-small text-fg-muted">
@if (! empty($project['district']))
<span class="inline-flex items-center gap-1.5">{!! \App\View\Icon::svg('MapPin', 'size-4 text-sindoor') !!}{{ $project['district'] }}</span>
@endif
@if (! empty($project['metaLine']))
<span>{{ $project['metaLine'] }}</span>
@endif
</p>
@endif
<{{ $pcH }} class="{{ \App\View\Html::cx('mt-3 font-display font-semibold text-heading', $pcFeature ? 'text-h2' : 'text-h3') }}"><a class="stretched-link focus-visible:outline-none" href="{{ lurl(\App\I18n\Routes::project($project['slug'])) }}">{{ $project['title'] }}</a></{{ $pcH }}>
@if (! empty($project['summary']))
<p class="{{ \App\View\Html::cx('mt-3 text-fg-muted', $pcFeature ? 'line-clamp-4 text-lead' : 'line-clamp-3') }}">{{ \App\View\Ui::todoText($project['summary']) }}</p>
@endif
@if (! empty($project['goalAmount']))
@partial('site.ui.progress-bar', ['class' => 'mt-5', 'raised' => $project['raisedAmount'] ?? 0, 'goal' => $project['goalAmount'], 'currency' => $project['currency'], 'locale' => $locale, 'compact' => ! $pcFeature])
@endif
<div class="mt-auto pt-5">
@if ($project['stage'] === 'ACTIVE')
<a class="relative z-[2] inline-flex min-h-11 items-center gap-1.5 font-semibold text-accent-text underline decoration-1 underline-offset-[5px] hover:decoration-2" href="{{ \App\View\Ui::href('/donate', ['project' => $project['slug']]) }}">{{ t('cta.supportProject') }}{!! \App\View\Icon::svg('ArrowRight', 'size-4') !!}<span class="sr-only">: {{ $project['title'] }}</span></a>
@else
<span class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-accent-text" aria-hidden="true">{{ t('cta.readMore') }} {!! \App\View\Icon::svg('ArrowRight', 'size-4') !!}</span>
@endif
</div>
</div>
</article>

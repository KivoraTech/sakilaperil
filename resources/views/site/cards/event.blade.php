{{--
    components/site/cards/event-card.tsx: date tile; past events get an "Archive" chip.
    Params: $event, $locale, $past, $headingLevel ('h3'), $class.
--}}
@php($ecH = $headingLevel ?? 'h3')
@php($ecTz = $event['timezone'] ?: 'Europe/Rome')
@php($ecPast = ! empty($past))
@php($ecDay = \App\I18n\Format::date($event['startsAt'], $locale, ['day' => 'numeric'], $ecTz))
@php($ecMonth = str_replace('.', '', \App\I18n\Format::date($event['startsAt'], $locale, ['month' => 'short'], $ecTz)))
@php($ecYear = \App\I18n\Format::date($event['startsAt'], $locale, ['year' => 'numeric'], $ecTz))
@php($ecWhen = \App\I18n\Format::date($event['startsAt'], $locale, ['weekday' => 'long', 'day' => 'numeric', 'month' => 'long'], $ecTz).($event['allDay'] ? '' : ' · '.\App\I18n\Format::time($event['startsAt'], $locale, $ecTz)))
<article class="{{ \App\View\Html::cx('group relative flex gap-5 rounded-lg bg-white p-5 shadow-card ring-1 ring-line/70 transition-shadow hover:shadow-raised md:p-6', $class ?? null) }}">
<div class="{{ \App\View\Html::cx('flex h-[4.75rem] w-16 shrink-0 flex-col items-center justify-center rounded-md text-center', $ecPast ? 'bg-sand text-himalaya' : 'bg-cream text-himalaya') }}" aria-hidden="true">
<span class="font-display text-[2rem] leading-none font-semibold">{{ $ecDay }}</span>
<span class="mt-1 text-[0.75rem] font-semibold tracking-[0.14em] uppercase">{{ $ecMonth }}</span>
@if ($ecPast)
<span class="text-[0.6875rem] text-ink-soft">{{ $ecYear }}</span>
@endif
</div>
<div class="min-w-0 flex-1">
<div class="flex flex-wrap gap-2">
@if (! empty($event['locationName']) && preg_match('/online/i', $event['locationName']))
@partial('site.ui.chip', ['tone' => 'sage', 'slot' => t('events.online')])
@endif
@if ($ecPast)
@partial('site.ui.chip', ['slot' => t('events.archive')])
@endif
</div>
<{{ $ecH }} class="mt-1 font-display text-h3 font-semibold text-heading"><a class="stretched-link link-sweep" href="{{ lurl(\App\I18n\Routes::event($event['slug'])) }}">{{ $event['title'] }}</a></{{ $ecH }}>
<ul class="mt-2 space-y-1 text-small text-fg-muted">
<li class="flex items-start gap-2">{!! \App\View\Icon::svg('Clock', 'mt-0.5 size-4 shrink-0 text-sindoor') !!}<time datetime="{{ $event['startsAt'] }}" class="first-letter:uppercase">{{ $ecWhen }}</time></li>
@if (! empty($event['locationName']))
<li class="flex items-start gap-2">{!! \App\View\Icon::svg('MapPin', 'mt-0.5 size-4 shrink-0 text-sindoor') !!}<span>{{ \App\View\Ui::todoText($event['locationName']) }}</span></li>
@endif
</ul>
@if (! empty($event['summary']))
<p class="mt-3 line-clamp-2 text-fg-muted">{{ \App\View\Ui::todoText($event['summary']) }}</p>
@endif
</div>
</article>

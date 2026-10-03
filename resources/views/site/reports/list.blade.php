{{--
    components/site/reports/report-list.tsx: transparency rows with year/type filters (every row is
    server-rendered; resources/js/site/reports.ts only filters). Params: $reports (+ sizeLabel).
--}}
@php($rlYears = array_values(array_unique(array_map(fn ($r) => $r['year'], $reports))))
@php(rsort($rlYears))
@php($rlTypes = array_values(array_unique(array_map(fn ($r) => $r['type'], $reports))))
@php($rlChip = fn (bool $active) => \App\View\Html::cx('inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold ring-1 ring-inset transition-colors', $active ? 'bg-himalaya text-white ring-himalaya' : 'bg-white text-himalaya ring-line-strong hover:bg-cream'))
<div data-report-list data-showing-zero="{{ t('reports.showing', ['count' => 0]) }}" data-showing-one="{{ t('reports.showing', ['count' => 1]) }}" data-showing-other="{{ t('reports.showing', ['count' => 987654321]) }}">
@if (count($reports) > 1)
<div class="flex flex-wrap items-center gap-3" role="group" aria-label="{{ t('reports.filters') }}">
<label class="flex items-center gap-2 text-small font-medium"><span>{{ t('reports.year') }}</span><select class="min-h-11 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-body" data-report-year><option value="all" selected="">{{ t('reports.allYears') }}</option>@foreach ($rlYears as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></label>
@if (count($rlTypes) > 1)
<button type="button" aria-pressed="true" class="{{ $rlChip(true) }}" data-report-type="all">{{ t('reports.allTypes') }}</button>
@foreach ($rlTypes as $ty)
<button type="button" aria-pressed="false" class="{{ $rlChip(false) }}" data-report-type="{{ $ty }}">{{ t('reports.type.'.$ty) }}</button>
@endforeach
@endif
</div>
@endif
<p class="sr-only" aria-live="polite" data-report-showing>{{ t('reports.showing', ['count' => count($reports)]) }}</p>
<ul class="mt-6 divide-y divide-line overflow-hidden rounded-lg bg-white shadow-card ring-1 ring-line/70">
@foreach ($reports as $r)
<li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center md:p-6" data-report-row data-year="{{ $r['year'] }}" data-type="{{ $r['type'] }}">
<span class="flex h-14 w-12 shrink-0 flex-col items-center justify-center rounded-md bg-sindoor-50 text-sindoor-700" aria-hidden="true">{!! \App\View\Icon::svg('FileText', 'size-5') !!}<span class="mt-0.5 text-[0.625rem] font-bold tracking-wider">PDF</span></span>
<div class="min-w-0 flex-1">
<p class="text-kicker font-semibold tracking-[0.12em] text-marigold-700 uppercase">{{ t('reports.type.'.$r['type']) }} · {{ $r['year'] }}</p>
<h3 class="mt-1 font-display text-xl font-semibold text-heading">{{ $r['title'] }}</h3>
@if (! empty($r['description']))
<p class="mt-1 line-clamp-2 text-small text-fg-muted">{{ $r['description'] }}</p>
@endif
@if (! empty($r['isFallback']))
<p class="mt-1 text-small text-fg-muted" lang="it">{{ t('reports.italianOnly') }}</p>
@endif
</div>
@if (! empty($r['file']))
<a href="{{ $r['file']['url'] }}" download="" class="inline-flex min-h-11 shrink-0 items-center gap-2 self-start rounded-full px-5 font-semibold text-himalaya ring-[1.5px] ring-himalaya ring-inset hover:bg-himalaya hover:text-white sm:self-center">{!! \App\View\Icon::svg('Download', 'size-4') !!}{{ t('reports.download') }}<span class="sr-only"> {{ $r['title'] }} ({{ $r['file']['mimeType'] === 'application/pdf' ? 'PDF' : $r['file']['mimeType'] }}{{ ! empty($r['sizeLabel']) ? ', '.$r['sizeLabel'] : '' }})</span>@if (! empty($r['sizeLabel']))<span aria-hidden="true" class="text-small font-normal opacity-80">({{ $r['sizeLabel'] }})</span>@endif</a>
@endif
</li>
@endforeach
@if (! count($reports))
<li class="p-6 text-fg-muted">{{ t('reports.none') }}</li>
@endif
</ul>
<template data-report-empty><li class="p-6 text-fg-muted">{{ t('reports.none') }}</li></template>
</div>

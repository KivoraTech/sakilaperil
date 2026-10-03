{{-- components/site/blocks/ReportsList.tsx: latest report featured + filterable document rows. --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($reports = array_map(fn ($r) => [...$r, 'sizeLabel' => ! empty($r['file']) ? \App\I18n\Format::fileSize($r['file']['size'], $loc) : null], \App\Queries\Public\Reports::list($loc, ['types' => $c['types'] ?? []])))
@php($latest = $reports[0] ?? null)
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="reportsList">
<x-site.container size="content">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'lead' => $c['lead'] ?? null])
@if ($latest)
<article class="surface-dark relative mt-10 grid overflow-hidden rounded-xl md:grid-cols-12" data-reveal="true">
<div class="relative min-h-44 bg-navy-800 md:col-span-4">
@if (! empty($latest['coverImage']))
{{ \App\View\Img::media($latest['coverImage'], sizes: '(min-width: 768px) 22rem, 100vw', alt: '') }}
@else
<div class="absolute inset-0 flex items-center justify-center" aria-hidden="true"><div class="flex h-36 w-28 flex-col justify-between rounded-md bg-paper p-3 shadow-panel"><span class="h-1.5 w-10 rounded bg-marigold"></span><span class="space-y-1.5"><span class="block h-1 rounded bg-line"></span><span class="block h-1 rounded bg-line"></span><span class="block h-1 w-2/3 rounded bg-line"></span></span><span class="font-display text-xl font-semibold text-himalaya">{{ $latest['year'] }}</span></div></div>
@endif
</div>
<div class="p-7 md:col-span-8 md:p-10">
<p class="kicker">{{ t('reports.latest') }}</p>
<h3 class="mt-3 font-display text-h3 font-semibold text-white">{{ $latest['title'] }}</h3>
@if (! empty($latest['description']))
<p class="mt-2 text-fg-muted">{{ $latest['description'] }}</p>
@endif
@if (! empty($latest['file']))
<a href="{{ $latest['file']['url'] }}" download="" class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-full bg-white px-6 font-semibold text-himalaya hover:bg-cream">{!! \App\View\Icon::svg('Download', 'size-5') !!}{{ t('reports.downloadPdf') }}<span class="text-small font-normal text-ink-soft">({{ $latest['sizeLabel'] }})</span><span class="sr-only"> {{ $latest['title'] }}</span></a>
@endif
</div>
</article>
@endif
<div class="mt-12">
@if (count($reports))
<h3 class="mb-5 flex items-center gap-2 font-display text-h3 font-semibold text-heading">{!! \App\View\Icon::svg('FileText', 'size-6 text-sindoor') !!}{{ t('reports.archive') }}</h3>
@partial('site.reports.list', ['reports' => $reports])
@else
<p class="rounded-lg bg-cream p-6 text-fg-muted">{{ t('reports.none') }}</p>
@endif
</div>
</x-site.container>
</x-site.section>

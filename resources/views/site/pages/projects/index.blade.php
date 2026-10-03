{{-- app/[locale]/(site)/projects/page.tsx --}}
@extends('layouts.site')

@section('content')
@php($chip = fn (bool $active) => \App\View\Html::cx('inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold ring-1 ring-inset transition-colors', $active ? 'bg-himalaya text-white ring-himalaya' : 'bg-white text-himalaya ring-line-strong hover:bg-cream'))
@php($q = fn (array $next) => \App\View\Ui::href('/projects', ['stage' => array_key_exists('stage', $next) ? $next['stage'] : $stageKey, 'sector' => array_key_exists('sector', $next) ? $next['sector'] : $sector]))
@php($feature = $current[0] ?? null)
@php($rest = array_slice($current, 1))
@partial('site.ui.page-header', ['kicker' => t('projects.kicker'), 'title' => t('projects.title'), 'accent' => t('projects.titleAccent'), 'lead' => t('projects.lead')])
<x-site.section spacing="compact">
<x-site.container>
<nav aria-label="{{ t('projects.filters') }}" class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
<ul class="flex flex-wrap gap-2">
<li><a class="{{ $chip(! $stageKey) }}"{!! ! $stageKey ? ' aria-current="true"' : '' !!} href="{{ $q(['stage' => null]) }}">{{ t('projects.allStages') }}</a></li>
@foreach (['active', 'completed'] as $k)
<li><a class="{{ $chip($stageKey === $k) }}"{!! $stageKey === $k ? ' aria-current="true"' : '' !!} href="{{ $q(['stage' => $k]) }}">{{ t('projects.stage.'.$k) }}</a></li>
@endforeach
</ul>
@if (count($sectors) > 1)
<ul class="flex flex-wrap gap-2">
<li><a class="{{ $chip(! $sector) }}"{!! ! $sector ? ' aria-current="true"' : '' !!} href="{{ $q(['sector' => null]) }}">{{ t('projects.allSectors') }}</a></li>
@foreach ($sectors as $sv)
<li><a class="{{ $chip($sector === $sv) }}"{!! $sector === $sv ? ' aria-current="true"' : '' !!} href="{{ $q(['sector' => $sv]) }}">{{ $sv }}</a></li>
@endforeach
</ul>
@endif
</nav>
<p class="sr-only" aria-live="polite">{{ t('projects.count', ['count' => count($filtered)]) }}</p>
@if ($feature)
<div class="mt-10 space-y-8">
@partial('site.cards.project', ['project' => $feature, 'locale' => $locale, 'variant' => 'feature', 'headingLevel' => 'h2', 'priority' => true])
@if (count($rest))
<ul class="grid gap-6 {{ \App\View\Ui::cardGrid(count($rest)) }}">
@foreach ($rest as $i => $p)
<li class="flex [&>*]:w-full" data-reveal="true" style="--reveal-i:{{ $i % 3 }}">@partial('site.cards.project', ['project' => $p, 'locale' => $locale, 'headingLevel' => 'h2'])</li>
@endforeach
</ul>
@endif
</div>
@else
<div class="mt-10">@partial('site.ui.empty-state', ['text' => t('projects.empty')])</div>
@endif
@partial('site.ui.pagination', ['page' => $pageNum, 'pageCount' => $pageCount, 'pathname' => '/projects', 'query' => ['stage' => $stageKey, 'sector' => $sector], 'labels' => ['nav' => t('pagination.nav'), 'prev' => t('pagination.prev'), 'next' => t('pagination.next')]])
</x-site.container>
</x-site.section>
@partial('site.donate.teaser', ['locale' => $locale])
@endsection

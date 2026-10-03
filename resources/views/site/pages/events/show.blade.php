{{-- app/[locale]/(site)/events/[slug]/page.tsx --}}
@extends('layouts.site')

@section('content')
@php($ext = t('a11y.externalLink'))
<article>
<header class="surface-cream pt-6 pb-12">
<x-site.container>
@partial('site.ui.breadcrumbs', ['label' => t('a11y.breadcrumb'), 'locale' => $locale, 'items' => [['name' => t('nav.home'), 'href' => '/'], ['name' => t('events.title'), 'href' => '/events'], ['name' => $event['title'], 'href' => $path]]])
<div class="mt-8 grid gap-10 lg:grid-cols-12">
<div class="lg:col-span-7">
@if ($isPast)
@partial('site.ui.chip', ['slot' => t('events.archive')])
@else
@partial('site.ui.chip', ['tone' => 'sage', 'slot' => t('events.upcomingChip')])
@endif
<h1 class="mt-4 font-display text-h1 font-semibold text-heading">{{ $event['title'] }}</h1>
@if (! empty($event['summary']))
<p class="mt-4 text-lead text-fg-muted">{{ \App\View\Ui::todoText($event['summary']) }}</p>
@endif
</div>
<aside class="rounded-lg bg-white p-6 shadow-card ring-1 ring-line/70 md:p-8 lg:col-span-5" aria-label="{{ t('events.details') }}">
<dl class="space-y-4">
<div class="relative pl-8"><dt class="sr-only">{{ t('events.when') }}</dt><dd>{!! \App\View\Icon::svg('Clock', 'absolute top-0.5 left-0 size-5 text-sindoor') !!}<time datetime="{{ $event['startsAt'] }}" class="block font-semibold text-fg first-letter:uppercase">{{ $dateLong }}</time><span class="text-small text-fg-muted">{{ $timeRange }} {{ ! $event['allDay'] && $tzName ? '('.$tzName.')' : '' }}</span></dd></div>
@if (! empty($event['locationName']) || ! empty($event['address']))
<div class="relative pl-8"><dt class="sr-only">{{ t('events.where') }}</dt><dd>{!! \App\View\Icon::svg('MapPin', 'absolute top-0.5 left-0 size-5 text-sindoor') !!}
@if (! empty($event['locationName']))
<span class="block font-semibold text-fg">{{ \App\View\Ui::todoText($event['locationName']) }}</span>
@endif
@if (! empty($event['address']))
<span class="block text-small text-fg-muted">{{ \App\View\Ui::todoText($event['address']) }}</span>
@endif
@if ($map)
<a href="{{ $map }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex min-h-11 items-center gap-1 text-small font-semibold text-accent-text underline underline-offset-4">{{ t('events.openMap') }}{!! \App\View\Icon::svg('ExternalLink', 'size-3.5') !!}<span class="sr-only"> {{ $ext }}</span></a>
@endif
</dd></div>
@endif
@if ($online)
<div class="relative pl-8"><dt class="sr-only">{{ t('events.online') }}</dt><dd>{!! \App\View\Icon::svg('Globe', 'absolute top-0.5 left-0 size-5 text-sindoor') !!}<a href="{{ $online }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-accent-text underline underline-offset-4">{{ t('events.joinOnline') }}<span class="sr-only"> {{ $ext }}</span></a></dd></div>
@endif
</dl>
<div class="mt-6 flex flex-col gap-3">
@if ($registration && ! $isPast)
<a href="{{ $registration }}" target="_blank" rel="noopener noreferrer" class="{{ \App\View\Ui::buttonClass('primary', 'md', 'w-full') }}">{{ t('events.register') }}<span class="sr-only"> {{ $ext }}</span></a>
@endif
@if (! $isPast)
<a href="/{{ $locale }}{{ $path }}/ics" download="" class="{{ \App\View\Ui::buttonClass('secondary', 'md', 'w-full') }}">{!! \App\View\Icon::svg('CalendarPlus', 'size-5') !!}{{ t('events.addToCalendar') }}</a>
@endif
</div>
</aside>
</div>
</x-site.container>
</header>
@if (! empty($event['coverImage']))
<x-site.container size="content" class="mt-12">
<div class="relative aspect-[16/9] overflow-hidden rounded-lg bg-sand">{{ \App\View\Img::media($event['coverImage'], sizes: '(min-width: 1024px) 64rem, 100vw', priority: true, containPortrait: true) }}</div>
</x-site.container>
@endif
<x-site.section spacing="compact">
<x-site.container size="prose">
@partial('site.ui.fallback-note', ['show' => $event['isFallback'], 'text' => t('common.italianOnly')])
<div{!! $event['isFallback'] ? ' lang="it"' : '' !!} class="{{ $event['isFallback'] ? 'mt-6' : '' }}">{{ \App\View\SiteRichText::content($event['body']) }}</div>
<div class="mt-10 border-t border-line pt-6">@partial('site.ui.share-links', ['url' => $url, 'title' => $event['title']])</div>
</x-site.container>
</x-site.section>
</article>
{{ \App\View\Html::jsonLd(\App\View\Seo::eventJsonLd($event, $settings, $locale, $url, $online ?: null)) }}
@endsection

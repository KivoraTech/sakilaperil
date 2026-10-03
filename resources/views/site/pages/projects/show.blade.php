{{-- app/[locale]/(site)/projects/[slug]/page.tsx --}}
@extends('layouts.site')

@section('content')
@php($stage = \App\View\Cards::STAGE_TONE[$project['stage']])
<div class="surface-cream pt-6 pb-12 md:pb-16">
<x-site.container>
@partial('site.ui.breadcrumbs', ['label' => t('a11y.breadcrumb'), 'locale' => $locale, 'items' => [['name' => t('nav.home'), 'href' => '/'], ['name' => t('projects.title'), 'href' => '/projects'], ['name' => $project['title'], 'href' => $path]]])
<div class="mt-6 grid gap-8 lg:grid-cols-12 lg:gap-10">
@if (! empty($project['coverImage']))
<div class="relative aspect-[16/10] overflow-hidden rounded-lg bg-sand shadow-raised lg:col-span-7 lg:aspect-auto lg:min-h-[28rem]">{{ \App\View\Img::media($project['coverImage'], sizes: '(min-width: 1024px) 58vw, 100vw', priority: true) }}</div>
@endif
<div id="project-summary" class="{{ \App\View\Html::cx('flex flex-col rounded-lg bg-white p-6 shadow-card ring-1 ring-line/70 md:p-8', ! empty($project['coverImage']) ? 'lg:col-span-5' : 'lg:col-span-12') }}">
<div class="flex flex-wrap gap-2">
@if (! empty($project['sector']))
@partial('site.ui.chip', ['slot' => $project['sector']])
@endif
@partial('site.ui.chip', ['tone' => 'sage', 'dot' => $stage['dot'], 'slot' => t('status.'.$stage['key'])])
</div>
<h1 class="mt-4 font-display text-h1 font-semibold text-heading">{{ $project['title'] }}</h1>
@if (! empty($project['summary']))
<p class="mt-4 text-lead text-fg-muted">{{ \App\View\Ui::todoText($project['summary']) }}</p>
@endif
<dl class="mt-6 grid gap-3 text-small sm:grid-cols-2">
@if (! empty($project['district']))
<div class="relative pl-6"><dt class="text-fg-muted">{!! \App\View\Icon::svg('MapPin', 'absolute top-0.5 left-0 size-4 text-sindoor') !!}{{ t('projects.district') }}</dt><dd class="font-semibold text-fg">{{ $project['district'] }}</dd></div>
@endif
@if (! empty($project['startDate']))
<div class="relative pl-6"><dt class="text-fg-muted">{!! \App\View\Icon::svg('CalendarDays', 'absolute top-0.5 left-0 size-4 text-sindoor') !!}{{ t('projects.started') }}</dt><dd class="font-semibold text-fg">{{ \App\I18n\Format::date($project['startDate'], $locale, ['month' => 'long', 'year' => 'numeric']) }}</dd></div>
@endif
@if (! empty($project['metaLine']))
<div class="relative pl-6 sm:col-span-2"><dt class="text-fg-muted">{!! \App\View\Icon::svg('Target', 'absolute top-0.5 left-0 size-4 text-sindoor') !!}{{ t('projects.keyFact') }}</dt><dd class="font-semibold text-fg">{{ $project['metaLine'] }}</dd></div>
@endif
</dl>
@if (! empty($project['goalAmount']))
@partial('site.ui.progress-bar', ['class' => 'mt-6', 'raised' => $project['raisedAmount'] ?? 0, 'goal' => $project['goalAmount'], 'currency' => $project['currency'], 'locale' => $locale])
@endif
@if ($canDonate)
<div class="mt-auto pt-7"><a class="{{ \App\View\Ui::buttonClass('primary', 'lg', 'w-full') }}" href="{{ lurl($donateHref) }}">{!! \App\View\Icon::svg('Heart', 'size-5') !!}{{ t('cta.supportProject') }}</a></div>
@endif
</div>
</div>
</x-site.container>
</div>
<x-site.section>
<x-site.container class="grid gap-12 lg:grid-cols-12">
<div class="lg:col-span-7 lg:col-start-1">
@partial('site.ui.fallback-note', ['show' => $project['isFallback'], 'text' => t('common.italianOnly')])
<div{!! $project['isFallback'] ? ' lang="it"' : '' !!} class="{{ $project['isFallback'] ? 'mt-6' : '' }}">
@if (! empty($project['body']['content']))
{{ \App\View\SiteRichText::content($project['body']) }}
@else
<p class="text-fg-muted">{{ t('projects.noBody') }}</p>
@endif
</div>
</div>
<aside class="lg:col-span-4 lg:col-start-9" aria-labelledby="updates-title">
<div class="lg:sticky lg:top-28">
<h2 id="updates-title" class="font-display text-h3 font-semibold text-heading">{{ t('projects.updates') }}</h2>
@if (count($updates['items']))
<ol class="relative mt-6 space-y-6 border-l-2 border-line pl-6">
@foreach ($updates['items'] as $u)
<li class="relative">
<span class="absolute top-1.5 -left-[1.95rem] size-3.5 rounded-full bg-sindoor ring-4 ring-bg" aria-hidden="true"></span>
@if (! empty($u['publishedAt']))
<time datetime="{{ $u['publishedAt'] }}" class="text-kicker font-semibold tracking-[0.12em] text-accent-text uppercase">{{ \App\I18n\Format::date($u['publishedAt'], $locale) }}</time>
@endif
<h3 class="mt-1 font-display text-lg leading-snug font-semibold text-heading"><a class="underline-offset-4 hover:underline" href="{{ lurl(\App\I18n\Routes::news($u['slug'])) }}">{{ $u['title'] }}</a></h3>
@if (! empty($u['excerpt']))
<p class="mt-1 line-clamp-2 text-small text-fg-muted">{{ $u['excerpt'] }}</p>
@endif
</li>
@endforeach
</ol>
@else
<p class="mt-4 rounded-lg bg-cream p-5 text-small text-fg-muted">{{ t('projects.noUpdates') }}</p>
@endif
</div>
</aside>
</x-site.container>
</x-site.section>
@if ($album && count($album['items']))
<x-site.section surface="cream" spacing="compact">
<x-site.container size="bleed">
<div class="flex flex-wrap items-end justify-between gap-4">
<h2 class="font-display text-h2 font-semibold text-heading">{{ t('projects.photos') }}</h2>
<a class="font-semibold text-accent-text underline underline-offset-4" href="{{ lurl(\App\I18n\Routes::album($album['slug'])) }}">{{ t('gallery.openAlbum') }}</a>
</div>
<div class="mt-8">@partial('site.gallery.grid', ['items' => array_slice($album['items'], 0, 8), 'layout' => 'grid'])</div>
</x-site.container>
</x-site.section>
@endif
@if ($canDonate)
@partial('site.donate.teaser', ['locale' => $locale, 'project' => ['slug' => $project['slug'], 'title' => $project['title']]])
@endif
@if (count($related))
<x-site.section>
<x-site.container>
<h2 class="font-display text-h2 font-semibold text-heading">{{ t('projects.related') }}</h2>
<ul class="mt-8 grid gap-6 {{ \App\View\Ui::cardGrid(count($related)) }}">
@foreach ($related as $p)
<li class="flex [&>*]:w-full">@partial('site.cards.project', ['project' => $p, 'locale' => $locale])</li>
@endforeach
</ul>
</x-site.container>
</x-site.section>
@endif
@if ($canDonate)
@partial('site.donate.sticky-bar', ['watchId' => 'project-summary', 'href' => $donateHref, 'label' => $project['title'], 'cta' => t('cta.donateShort')])
@endif
@endsection

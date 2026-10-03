{{--
    Port of app/preview/[locale]/events/[id]/page.tsx: the SAVED draft of an event with the public
    site's chrome and components. Params: $event (EventDetail + status), $locale, $meta, $banner.
--}}
@extends('layouts.site')

@push('body-start')
    @include('preview.content.banner')
@endpush

@section('content')
@php($tz = $event['timezone'] ?: 'Europe/Rome')
@php($dateLong = \App\I18n\Format::date($event['startsAt'], $locale, ['weekday' => 'long', 'day' => 'numeric', 'month' => 'long', 'year' => 'numeric'], $tz))
@php($timeRange = $event['allDay'] ? t('events.allDay') : \App\I18n\Format::time($event['startsAt'], $locale, $tz).($event['endsAt'] ? '–'.\App\I18n\Format::time($event['endsAt'], $locale, $tz) : ''))
<article><header class="surface-cream pt-10 pb-12"><x-site.container><div class="grid gap-10 lg:grid-cols-12"><div class="lg:col-span-7">@include('site.ui.chip', ['tone' => 'sage', 'slot' => t('events.upcomingChip')])<h1 class="mt-4 font-display text-h1 font-semibold text-heading">{{ $event['title'] }}</h1>@if ($event['summary'])<p class="mt-4 text-lead text-fg-muted">{{ \App\View\Ui::todoText($event['summary']) }}</p>@endif</div><aside class="rounded-lg bg-white p-6 shadow-card ring-1 ring-line/70 md:p-8 lg:col-span-5" aria-label="{{ t('events.details') }}"><dl class="space-y-4"><div class="flex gap-3">{!! \App\View\Icon::svg('Clock', 'mt-0.5 size-5 shrink-0 text-sindoor') !!}<div><dt class="sr-only">{{ t('events.when') }}</dt><dd><time datetime="{{ $event['startsAt'] }}" class="block font-semibold text-fg first-letter:uppercase">{{ $dateLong }}</time><span class="text-small text-fg-muted">{{ $timeRange }}</span></dd></div></div>@if ($event['locationName'] || $event['address'])<div class="flex gap-3">{!! \App\View\Icon::svg('MapPin', 'mt-0.5 size-5 shrink-0 text-sindoor') !!}<div><dt class="sr-only">{{ t('events.where') }}</dt><dd>@if ($event['locationName'])<span class="block font-semibold text-fg">{{ \App\View\Ui::todoText($event['locationName']) }}</span>@endif @if ($event['address'])<span class="block text-small text-fg-muted">{{ \App\View\Ui::todoText($event['address']) }}</span>@endif</dd></div></div>@endif @if ($event['onlineUrl'])<div class="flex gap-3">{!! \App\View\Icon::svg('Globe', 'mt-0.5 size-5 shrink-0 text-sindoor') !!}<div><dt class="sr-only">{{ t('events.online') }}</dt><dd class="font-semibold text-accent-text">{{ t('events.joinOnline') }}</dd></div></div>@endif</dl></aside></div></x-site.container></header>@if ($event['coverImage'])<x-site.container size="content" class="mt-12"><div class="relative aspect-[16/9] overflow-hidden rounded-lg bg-sand">{{ \App\View\Img::media($event['coverImage'], '(min-width: 1024px) 64rem, 100vw', priority: true) }}</div></x-site.container>@endif<x-site.section spacing="compact"><x-site.container size="prose">@include('preview.content.fallback-note', ['show' => $event['isFallback']])<div @if ($event['isFallback']) lang="it" @endif class="{{ $event['isFallback'] ? 'mt-6' : '' }}">{{ \App\View\SiteRichText::content($event['body']) }}</div></x-site.container></x-site.section></article>
@endsection

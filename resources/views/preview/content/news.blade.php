{{--
    Port of app/preview/[locale]/news/[id]/page.tsx: the SAVED draft of a news story rendered with the
    public site's chrome and components (article part of the public news detail page). Session-gated,
    noindex, no-store. Params: $post (NewsDetail + status), $locale, $meta, $banner.
--}}
@extends('layouts.site')

@push('body-start')
    @include('preview.content.banner')
@endpush

@section('content')
@php($minutes = \App\I18n\Format::readingMinutes(\App\View\SiteRichText::countWords($post['body'])))
<article><header class="pt-10"><x-site.container size="prose" class="text-center">@if ($post['category'])@include('site.ui.chip', ['slot' => $post['category']['name']])@endif<h1 class="mt-5 font-display text-h1 font-semibold text-heading">{{ $post['title'] }}</h1>@if ($post['excerpt'])<p class="mt-5 text-lead text-fg-muted">{{ \App\View\Ui::todoText($post['excerpt']) }}</p>@endif<p class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-small text-fg-muted">@if ($post['authorName'])<span class="inline-flex items-center gap-1.5">{!! \App\View\Icon::svg('UserRound', 'size-4') !!}{{ $post['authorName'] }}</span>@endif @if ($post['publishedAt'])<time datetime="{{ $post['publishedAt'] }}">{{ \App\I18n\Format::date($post['publishedAt'], $locale) }}</time>@endif<span class="inline-flex items-center gap-1.5">{!! \App\View\Icon::svg('Clock', 'size-4') !!}{{ t('news.readingTime', ['minutes' => $minutes]) }}</span></p></x-site.container>@if ($post['coverImage'])<x-site.container size="content" class="mt-10"><figure><div class="relative aspect-[16/9] overflow-hidden rounded-lg bg-sand shadow-raised">{{ \App\View\Img::media($post['coverImage'], '(min-width: 1024px) 64rem, 100vw', priority: true) }}</div>@if ($post['coverImage']['alt'])<figcaption class="mt-3 text-small text-fg-muted">{{ $post['coverImage']['alt'] }}</figcaption>@endif</figure></x-site.container>@endif</header><x-site.section spacing="compact"><x-site.container size="prose">@include('preview.content.fallback-note', ['show' => $post['isFallback']])<div @if ($post['isFallback']) lang="it" @endif class="{{ $post['isFallback'] ? 'mt-6' : '' }}">{{ \App\View\SiteRichText::content($post['body'], '[&>p:first-child]:text-lead') }}</div>@if (count($post['tags']))<ul class="mt-10 flex flex-wrap gap-2" aria-label="{{ t('news.tags') }}">@foreach ($post['tags'] as $tag)<li>@include('site.ui.chip', ['slot' => '#'.$tag['name']])</li>@endforeach</ul>@endif</x-site.container></x-site.section></article>
@endsection

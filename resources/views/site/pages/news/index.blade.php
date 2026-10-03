{{-- app/[locale]/(site)/news/page.tsx --}}
@extends('layouts.site')

@section('content')
@php($chip = fn (bool $active) => \App\View\Html::cx('inline-flex min-h-11 items-center rounded-full px-4 text-small font-semibold ring-1 ring-inset transition-colors', $active ? 'bg-himalaya text-white ring-himalaya' : 'bg-white text-himalaya ring-line-strong hover:bg-cream'))
@partial('site.ui.page-header', ['kicker' => t('news.kicker'), 'title' => t('news.title'), 'accent' => t('news.titleAccent'), 'lead' => t('news.lead')])
<x-site.section spacing="compact">
<x-site.container>
@if (count($categories) > 1)
<nav aria-label="{{ t('news.filters') }}">
<ul class="flex flex-wrap gap-2">
<li><a class="{{ $chip($category === null) }}"{!! $category === null ? ' aria-current="true"' : '' !!} href="{{ lurl('/news') }}">{{ t('news.allCategories') }}</a></li>
@foreach ($categories as $cat)
<li><a class="{{ $chip(($category['id'] ?? null) === $cat['id']) }}"{!! ($category['id'] ?? null) === $cat['id'] ? ' aria-current="true"' : '' !!} href="{{ \App\View\Ui::href('/news', ['category' => $cat['slug']]) }}">{{ $cat['name'] }}</a></li>
@endforeach
</ul>
</nav>
@endif
@if ($feature)
<div class="mt-10">@partial('site.cards.news', ['post' => $feature, 'locale' => $locale, 'variant' => 'feature', 'headingLevel' => 'h2'])</div>
@endif
@if (count($rest))
<ul class="mt-12 grid gap-x-6 gap-y-12 {{ \App\View\Ui::cardGrid(count($rest)) }}">
@foreach ($rest as $i => $post)
<li data-reveal="true" style="--reveal-i:{{ $i % 3 }}">@partial('site.cards.news', ['post' => $post, 'locale' => $locale, 'headingLevel' => 'h2'])</li>
@endforeach
</ul>
@endif
@if (! count($items))
<div class="mt-10">@partial('site.ui.empty-state', ['text' => t('news.empty')])</div>
@endif
@partial('site.ui.pagination', ['page' => $pageNum, 'pageCount' => $pageCount, 'pathname' => '/news', 'query' => ['category' => $category['slug'] ?? null], 'labels' => ['nav' => t('pagination.nav'), 'prev' => t('pagination.prev'), 'next' => t('pagination.next')]])
</x-site.container>
</x-site.section>
{{ \App\View\Blocks::renderOne(['id' => 'news-newsletter', 'type' => 'newsletter', 'content' => ['heading' => t('news.newsletterHeading'), 'headingAccent' => t('news.newsletterAccent'), 'body' => t('news.newsletterBody')], 'settings' => ['tone' => 'himalaya', 'background' => 'paper']], $locale, 2) }}
@endsection

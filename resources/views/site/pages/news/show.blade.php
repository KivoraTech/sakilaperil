{{-- app/[locale]/(site)/news/[slug]/page.tsx --}}
@extends('layouts.site')

@section('content')
<article>
<header class="pt-6">
<x-site.container size="content">
@partial('site.ui.breadcrumbs', ['label' => t('a11y.breadcrumb'), 'locale' => $locale, 'items' => [['name' => t('nav.home'), 'href' => '/'], ['name' => t('news.title'), 'href' => '/news'], ['name' => $post['title'], 'href' => $path]]])
</x-site.container>
<x-site.container size="prose" class="mt-8 text-center">
@if (! empty($post['category']))
@partial('site.ui.chip', ['slot' => $post['category']['name']])
@endif
<h1 class="mt-5 font-display text-h1 font-semibold text-heading">{{ $post['title'] }}</h1>
@if (! empty($post['excerpt']))
<p class="mt-5 text-lead text-fg-muted">{{ \App\View\Ui::todoText($post['excerpt']) }}</p>
@endif
<p class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-small text-fg-muted">
@if (! empty($post['authorName']))
<span class="inline-flex items-center gap-1.5">{!! \App\View\Icon::svg('UserRound', 'size-4') !!}{{ $post['authorName'] }}</span>
@endif
@if (! empty($post['publishedAt']))
<time datetime="{{ $post['publishedAt'] }}">{{ \App\I18n\Format::date($post['publishedAt'], $locale) }}</time>
@endif
<span class="inline-flex items-center gap-1.5">{!! \App\View\Icon::svg('Clock', 'size-4') !!}{{ t('news.readingTime', ['minutes' => $minutes]) }}</span>
</p>
</x-site.container>
@if (! empty($post['coverImage']))
<x-site.container size="content" class="mt-10">
<figure>
<div class="relative aspect-[16/9] overflow-hidden rounded-lg bg-sand shadow-raised">{{ \App\View\Img::media($post['coverImage'], sizes: '(min-width: 1024px) 64rem, 100vw', priority: true, containPortrait: true) }}</div>
@if (! empty($post['coverImage']['alt']))
<figcaption class="mt-3 text-small text-fg-muted">{{ $post['coverImage']['alt'] }}</figcaption>
@endif
</figure>
</x-site.container>
@endif
</header>
<x-site.section spacing="compact">
<x-site.container size="prose">
@partial('site.ui.fallback-note', ['show' => $post['isFallback'], 'text' => t('common.italianOnly')])
<div{!! $post['isFallback'] ? ' lang="it"' : '' !!} class="{{ $post['isFallback'] ? 'mt-6' : '' }}">{{ \App\View\SiteRichText::content($post['body'], '[&>p:first-child]:text-lead') }}</div>
@if (count($post['tags']))
<ul class="mt-10 flex flex-wrap gap-2" aria-label="{{ t('news.tags') }}">
@foreach ($post['tags'] as $tag)
<li>@partial('site.ui.chip', ['slot' => '#'.$tag['name']])</li>
@endforeach
</ul>
@endif
<div class="mt-10 border-t border-line pt-6">@partial('site.ui.share-links', ['url' => $url, 'title' => $post['title']])</div>
</x-site.container>
</x-site.section>
</article>
@if ($project)
<x-site.section surface="cream" spacing="compact">
<x-site.container size="content">
<p class="kicker">{{ t('news.aboutProject') }}</p>
<div class="mt-6">@partial('site.cards.project', ['project' => $project, 'locale' => $locale, 'variant' => 'feature', 'headingLevel' => 'h2'])</div>
</x-site.container>
</x-site.section>
@endif
@partial('site.donate.teaser', ['locale' => $locale, 'project' => $project ? ['slug' => $project['slug'], 'title' => $project['title']] : null])
@if (count($related))
<x-site.section>
<x-site.container>
<h2 class="font-display text-h2 font-semibold text-heading">{{ t('news.related') }}</h2>
<ul class="mt-8 grid gap-x-6 gap-y-10 {{ \App\View\Ui::cardGrid(count($related)) }}">
@foreach ($related as $p)
<li>@partial('site.cards.news', ['post' => $p, 'locale' => $locale])</li>
@endforeach
</ul>
</x-site.container>
</x-site.section>
@endif
{{ \App\View\Html::jsonLd(\App\View\Seo::newsArticleJsonLd($post, $settings, $locale, $url, \App\View\Brand::images($settings)['logo'])) }}
@endsection

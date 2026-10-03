{{-- app/[locale]/(site)/gallery/[slug]/page.tsx --}}
@extends('layouts.site')

@section('content')
@php($crumbs = view('site.ui.breadcrumbs', ['label' => t('a11y.breadcrumb'), 'locale' => $locale, 'items' => [['name' => t('nav.home'), 'href' => '/'], ['name' => t('gallery.title'), 'href' => '/gallery'], ['name' => $album['title'], 'href' => \App\I18n\Routes::album($album['slug'])]]])->render())
@php($meta2 = '<p class="mt-4 text-small text-fg-muted">'.e(t('gallery.mediaCount', ['photos' => $photos, 'videos' => $videos])).(! empty($album['takenAt']) ? ' · '.e(\App\I18n\Format::date($album['takenAt'], $locale, ['month' => 'long', 'year' => 'numeric'])) : '').'</p><div class="mt-4">'.view('site.ui.fallback-note', ['show' => $album['isFallback'], 'text' => t('common.italianOnly')])->render().'</div>')
@partial('site.ui.page-header', ['title' => $album['title'], 'lead' => $album['description'], 'breadcrumbs' => new \Illuminate\Support\HtmlString($crumbs), 'children' => new \Illuminate\Support\HtmlString($meta2)])
<x-site.section spacing="compact">
<x-site.container size="bleed">
@partial('site.gallery.grid', ['items' => $album['items'], 'layout' => 'masonry', 'wide' => true, 'syncUrl' => true])
@if ($prev || $next)
<nav aria-label="{{ t('gallery.albumNav') }}" class="mt-14 flex flex-wrap justify-between gap-4 border-t border-line pt-6">
@if ($prev)
<a class="group min-h-11 max-w-[45%]" href="{{ lurl(\App\I18n\Routes::album($prev['slug'])) }}"><span class="block text-small text-fg-muted">← {{ t('gallery.prevAlbum') }}</span><span class="font-display text-xl font-semibold text-heading group-hover:underline">{{ $prev['title'] }}</span></a>
@else
<span></span>
@endif
@if ($next)
<a class="group min-h-11 max-w-[45%] text-right" href="{{ lurl(\App\I18n\Routes::album($next['slug'])) }}"><span class="block text-small text-fg-muted">{{ t('gallery.nextAlbum') }} →</span><span class="font-display text-xl font-semibold text-heading group-hover:underline">{{ $next['title'] }}</span></a>
@endif
</nav>
@endif
</x-site.container>
</x-site.section>
@endsection

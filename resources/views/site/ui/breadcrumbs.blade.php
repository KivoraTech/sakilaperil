{{-- components/site/ui/breadcrumbs.tsx: detail pages + BreadcrumbList JSON-LD. Params: $items [{name, href}], $label, $locale, $tone. --}}
@php($bcDark = ($tone ?? 'light') === 'dark')
<nav aria-label="{{ $label }}">
<ol class="flex flex-wrap items-center gap-x-1 gap-y-1 text-small">
@foreach ($items as $bcI => $c)
<li class="flex min-w-0 items-center gap-1">
@if ($bcI === count($items) - 1)
<span aria-current="page" class="{{ $bcDark ? 'truncate text-white/85' : 'truncate text-fg-muted' }}">{{ $c['name'] }}</span>
@else
<a class="{{ $bcDark ? 'inline-flex min-h-11 items-center font-medium text-white underline-offset-4 hover:underline' : 'inline-flex min-h-11 items-center font-medium text-himalaya underline-offset-4 hover:underline' }}" href="{{ lurl($c['href'], $locale) }}">{{ $c['name'] }}</a>{!! \App\View\Icon::svg('ChevronRight', $bcDark ? 'size-4 text-white/70' : 'size-4 text-line-strong') !!}
@endif
</li>
@endforeach
</ol>
</nav>
{{ \App\View\Html::jsonLd(\App\View\Seo::breadcrumbJsonLd(array_map(fn ($c) => ['name' => $c['name'], 'path' => \App\View\Seo::localePath($locale, $c['href'])], $items))) }}

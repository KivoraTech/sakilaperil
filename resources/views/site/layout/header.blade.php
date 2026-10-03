{{--
    components/site/layout/site-header.tsx + header-nav.tsx: logo · CMS nav (xl+) · language switch ·
    Donate (every width) · menu button under xl. Transparent over a dark home hero until the visitor
    scrolls (CSS :has()). Islands (resources/js/site/header.ts): scroll state, hide-on-scroll-down,
    dropdowns, the mobile <dialog>.
    Params: $settings, $locale, $alternates (nullable).
--}}
@php($nav = \App\Queries\Public\Navigation::get('header', $locale))
@php($orgName = (string) $settings['orgName'])
@php($pathname = \App\View\Nav::pathname())
@php($labels = ['mainNav' => t('a11y.mainNav'), 'mobileNav' => t('a11y.mobileNav'), 'openMenu' => t('a11y.openMenu'), 'closeMenu' => t('a11y.closeMenu'), 'language' => t('a11y.languageSwitcher'), 'donate' => t('cta.donate'), 'newTab' => t('a11y.externalLink')])
<header id="site-header" class="site-header sticky top-0 z-40">
<div class="mx-auto flex h-16 max-w-bleed items-center gap-3 px-gutter md:h-18">
<a class="nav-in -m-1 mr-auto flex items-center gap-3 rounded-lg p-1" style="--i:0" aria-label="{{ $orgName }} — {{ t('nav.home') }}" href="{{ lurl('/') }}">
@partial('site.brand.logo-mark', ['class' => 'size-10 md:size-11'])
@partial('site.brand.wordmark', ['name' => $orgName])
</a>
<nav aria-label="{{ $labels['mainNav'] }}" class="hidden xl:block">
<ul class="flex items-center gap-1">
@foreach ($nav as $i => $item)
@if (count($item['children']))
@php($ddActive = collect([$item, ...$item['children']])->contains(fn ($c) => ! $c['external'] && \App\View\Nav::isActive($pathname, $c['href'])))
<li class="nav-in relative" style="--i:{{ $i + 1 }}" data-dropdown>
<button type="button" aria-expanded="false"@if ($ddActive) data-active="true"@endif class="nav-link inline-flex items-center gap-1">{{ $item['label'] }}{!! \App\View\Icon::svg('ChevronDown', 'size-4 transition-transform') !!}</button>
<template data-dropdown-menu>
<ul class="absolute top-full left-1/2 z-10 mt-2 min-w-56 -translate-x-1/2 rounded-xl bg-white p-2 shadow-raised ring-1 ring-line">
@foreach ([$item, ...$item['children']] as $c)
<li>
@partial('site.layout.nav-item-link', ['item' => $c, 'pathname' => $pathname, 'newTab' => $labels['newTab'], 'class' => 'block rounded-lg px-4 py-2.5 font-medium text-himalaya hover:bg-cream aria-[current=page]:bg-cream'])
</li>
@endforeach
</ul>
</template>
</li>
@else
<li class="nav-in" style="--i:{{ $i + 1 }}">
@partial('site.layout.nav-item-link', ['item' => $item, 'pathname' => $pathname, 'newTab' => $labels['newTab'], 'class' => 'nav-link'])
</li>
@endif
@endforeach
</ul>
</nav>
<div class="nav-in ml-2 hidden sm:block" style="--i:6">
@partial('site.layout.language-switch', ['label' => $labels['language'], 'alternates' => $alternates ?? [], 'pathname' => $pathname, 'locale' => $locale])
</div>
<a class="{{ \App\View\Ui::buttonClass('primary', 'sm', 'nav-in shrink-0 md:min-h-12 md:px-6') }}" style="--i:7" href="{{ lurl('/donate') }}">
{!! \App\View\Icon::svg('Heart', 'size-4') !!}
<span class="md:hidden">{{ t('cta.donateShort') }}</span>
<span class="hidden md:inline">{{ t('cta.donate') }}</span>
</a>
<button type="button" class="header-icon-btn xl:hidden" aria-haspopup="dialog" aria-expanded="false" aria-controls="site-menu" data-menu-open>
{!! \App\View\Icon::svg('Menu', 'size-6') !!}
<span class="sr-only">{{ $labels['openMenu'] }}</span>
</button>
<dialog id="site-menu" aria-label="{{ $labels['mobileNav'] }}" class="menu-sheet surface-dark m-0 ml-auto h-dvh max-h-none w-[min(26rem,100vw)] max-w-none p-0 text-fg" data-menu>
<div class="flex h-full flex-col">
<div class="flex items-center justify-between px-6 pt-4">
<span class="font-display text-lg text-white">{{ $orgName }}</span>
<button type="button" class="header-icon-btn text-white" data-menu-close>
{!! \App\View\Icon::svg('X', 'size-6') !!}
<span class="sr-only">{{ $labels['closeMenu'] }}</span>
</button>
</div>
<nav aria-label="{{ $labels['mobileNav'] }}" class="flex-1 overflow-y-auto px-6 py-6">
<ul class="space-y-1">
@foreach ($nav as $item)
@foreach (count($item['children']) ? [$item, ...$item['children']] : [$item] as $flat)
<li>
@partial('site.layout.nav-item-link', ['item' => $flat, 'pathname' => $pathname, 'newTab' => $labels['newTab'], 'class' => 'block rounded-lg py-2.5 font-display text-[1.75rem] leading-tight text-white decoration-marigold decoration-2 underline-offset-8 hover:underline aria-[current=page]:text-gold aria-[current=page]:underline'])
</li>
@endforeach
@endforeach
</ul>
</nav>
<div class="space-y-4 border-t border-white/10 px-6 py-6">
@partial('site.layout.language-switch', ['label' => $labels['language'], 'tone' => 'dark', 'alternates' => $alternates ?? [], 'pathname' => $pathname, 'locale' => $locale])
<a class="flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-sindoor text-lg font-semibold text-white shadow-cta hover:bg-sindoor-700" href="{{ lurl('/donate') }}" data-menu-close>
{!! \App\View\Icon::svg('Heart', 'size-5') !!}
{{ $labels['donate'] }}
</a>
</div>
</div>
</dialog>
</div>
</header>

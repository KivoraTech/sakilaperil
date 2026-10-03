{{--
    components/site/layout/language-switch.tsx: IT | EN as real links that keep the equivalent page
    ($alternates: locale-less path per locale for pages with localized slugs; otherwise the same path).
    Params: $label, $tone ('auto'|'dark'), $class, $alternates, $pathname, $locale.
--}}
@php($lsTone = $tone ?? 'auto')
<nav aria-label="{{ $label }}"@if (! empty($class)) class="{{ $class }}"@endif>
<ul class="{{ \App\View\Html::cx('flex items-center rounded-full p-1 ring-1 ring-inset', $lsTone === 'dark' ? 'bg-white/10 ring-white/25' : 'lang-switch bg-(--lang-bg) ring-(--lang-ring)') }}">
@foreach (\App\I18n\Locales::ALL as $l)
@php($lsActive = $l === $locale)
@php($lsHref = $alternates[$l] ?? $pathname)
<li>
<a hreflang="{{ $l }}" lang="{{ $l }}"@if ($lsActive) aria-current="true"@endif class="{{ \App\View\Html::cx('flex min-h-9 min-w-11 items-center justify-center rounded-full px-3 text-[0.8125rem] font-semibold tracking-wide uppercase transition-colors', $lsActive ? 'bg-white text-himalaya shadow-card' : ($lsTone === 'dark' ? 'text-white hover:bg-white/10' : 'text-(--lang-fg) hover:bg-(--lang-hover)')) }}" href="{{ lurl($lsHref, $l) }}">
<span aria-hidden="true">{{ strtoupper($l) }}</span>
<span class="sr-only">{{ \App\I18n\Locales::LABELS[$l] }}</span>
</a>
</li>
@endforeach
</ul>
</nav>

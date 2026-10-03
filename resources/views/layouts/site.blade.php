{{--
    Public site chrome (port of app/[locale]/(site)/layout.tsx): skip link, demo notice, sticky
    header, main landmark, footer, NGO JSON-LD. `data-motion` (from settings.appearance.enableMotion)
    gates every decorative animation in CSS; resources/js/site/motion.ts is the motion island.
    Variables: $locale, $meta (App\View\Seo::build), optional $alternates (locale-less path per locale,
    used by the language switch), $settings (defaults to SiteSettings).
--}}
@extends('layouts.document')

@section('document')
@php($settings = $settings ?? app(\App\Settings\SiteSettings::class)->get())
@php($siteLocale = $locale ?? \App\I18n\Locales::current())
<div class="site flex min-h-dvh flex-col" @if ($settings['appearance']['enableMotion'] ?? true) data-motion="" @endif>
<a href="#main" class="sr-only z-[60] rounded-full bg-himalaya px-5 py-3 font-semibold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ t('a11y.skipToContent') }}</a>
@if (! empty($settings['features']['demoContent']))
<p class="bg-warning-bg px-gutter py-1.5 text-center text-[0.8125rem] leading-snug font-medium text-warning">{{ t('common.demoNotice') }}</p>
@endif
@partial('site.layout.header', ['settings' => $settings, 'locale' => $siteLocale, 'alternates' => $alternates ?? null])
<main id="main" tabindex="-1" class="flex-1 outline-none">
@yield('content')
</main>
@partial('site.layout.footer', ['settings' => $settings, 'locale' => $siteLocale])
{{ \App\View\Html::jsonLd(\App\View\Seo::organizationJsonLd($settings, $siteLocale, \App\View\Brand::images($settings)['logo'])) }}
</div>
@endsection

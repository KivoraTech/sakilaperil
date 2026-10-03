{{-- app/[locale]/(site)/page.tsx --}}
@extends('layouts.site')

@section('content')
{{ \App\View\Blocks::render($page['sections'], $locale) }}
{{ \App\View\Html::jsonLd(\App\View\Seo::websiteJsonLd(app(\App\Settings\SiteSettings::class)->get(), $locale)) }}
@endsection

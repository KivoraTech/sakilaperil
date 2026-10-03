{{-- app/[locale]/(site)/[...slug]/page.tsx: CMS page; a page header unless the first section is a hero. --}}
@extends('layouts.site')

@section('content')
@if (! \App\View\Blocks::startsWithHero($page['sections']))
@partial('site.ui.page-header', ['title' => $page['title'], 'lead' => $page['seoDescription'] ?? null])
@endif
{{ \App\View\Blocks::render($page['sections'], $locale) }}
@endsection

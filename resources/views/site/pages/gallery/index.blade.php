{{-- app/[locale]/(site)/gallery/page.tsx --}}
@extends('layouts.site')

@section('content')
@partial('site.ui.page-header', ['kicker' => t('gallery.kicker'), 'title' => t('gallery.title'), 'accent' => t('gallery.titleAccent'), 'lead' => t('gallery.lead')])
<x-site.section spacing="compact">
<x-site.container>
@if (count($albums))
<ul class="grid gap-x-6 gap-y-12 {{ \App\View\Ui::cardGrid(count($albums)) }}">
@foreach ($albums as $i => $a)
<li data-reveal="true" style="--reveal-i:{{ $i % 3 }}">@partial('site.cards.album', ['album' => $a, 'locale' => $locale, 'priority' => $i < 3, 'headingLevel' => 'h2'])</li>
@endforeach
</ul>
@else
@partial('site.ui.empty-state', ['text' => t('gallery.empty')])
@endif
</x-site.container>
</x-site.section>
@endsection

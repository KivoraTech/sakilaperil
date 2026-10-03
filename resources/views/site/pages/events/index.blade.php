{{-- app/[locale]/(site)/events/page.tsx --}}
@extends('layouts.site')

@section('content')
@partial('site.ui.page-header', ['kicker' => t('events.kicker'), 'title' => t('events.title'), 'lead' => t('events.lead')])
<x-site.section spacing="compact">
<x-site.container size="content">
<h2 class="font-display text-h2 font-semibold text-heading">{{ t('events.upcoming') }}</h2>
@if (count($upcoming))
<div class="mt-8 space-y-10">
@foreach ($groups as $month => $events)
<section aria-label="{{ $month }}">
<h3 class="kicker first-letter:uppercase">{{ $month }}</h3>
<ul class="mt-4 space-y-4">
@foreach ($events as $e)
<li data-reveal="true">@partial('site.cards.event', ['event' => $e, 'locale' => $locale])</li>
@endforeach
</ul>
</section>
@endforeach
</div>
@else
<div class="mt-8">@partial('site.ui.empty-state', ['text' => t('events.empty'), 'action' => \App\View\Ui::buttonLink('#newsletter-footer', t('events.subscribe'), variant: 'secondary')])</div>
@endif
@if (count($past))
{{-- With nothing upcoming, the past events ARE the content of this page: show them open. --}}
<details class="group mt-16 rounded-xl bg-cream p-2" @if (! count($upcoming)) open @endif>
<summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 rounded-lg px-4 font-display text-h3 font-semibold text-heading [&::-webkit-details-marker]:hidden">{{ t('events.past') }} ({{ count($past) }}){!! \App\View\Icon::svg('ChevronDown', 'size-6 transition-transform group-open:rotate-180') !!}</summary>
<ul class="space-y-4 p-2 pt-4">
@foreach ($past as $e)
<li>@partial('site.cards.event', ['event' => $e, 'locale' => $locale, 'past' => true])</li>
@endforeach
</ul>
</details>
@endif
</x-site.container>
</x-site.section>
@endsection

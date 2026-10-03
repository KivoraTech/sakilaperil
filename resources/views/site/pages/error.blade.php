{{-- app/[locale]/(site)/error.tsx: render error inside the site chrome. "Retry" reloads the page
     (the reference's client `reset()`); rendered by bootstrap/app.php for public 5xx. --}}
@extends('layouts.site')

@section('content')
<section role="alert" class="py-section">
<div class="mx-auto max-w-prose px-gutter text-center">
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'mx-auto max-w-md text-line-strong'])
<h1 class="mt-10 font-display text-h1 font-semibold text-heading">{{ t('errors.errorTitle') }}</h1>
<p class="mx-auto mt-4 max-w-md text-lead text-fg-muted">{{ t('errors.errorBody') }}</p>
<div class="mt-8 flex flex-wrap justify-center gap-3">
<a href="{{ request()->getRequestUri() }}" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-sindoor px-6 font-semibold text-white shadow-cta hover:bg-sindoor-700">{!! \App\View\Icon::svg('RotateCcw', 'size-5') !!}{{ t('errors.retry') }}</a>
<a href="{{ lurl('/') }}" class="inline-flex min-h-12 items-center rounded-full px-6 font-semibold text-himalaya ring-[1.5px] ring-himalaya ring-inset hover:bg-himalaya hover:text-white">{{ t('cta.backHome') }}</a>
</div>
</div>
</section>
@endsection

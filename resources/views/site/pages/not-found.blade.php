{{-- app/[locale]/(site)/not-found.tsx: 404 inside the site chrome. --}}
@extends('layouts.site')

@section('content')
<section class="py-section">
<x-site.container size="prose" class="text-center">
<div class="relative mx-auto max-w-md text-line-strong" aria-hidden="true">
@partial('site.brand.flag-string', ['class' => 'absolute -top-3 left-1/2 w-40 -translate-x-1/2'])
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'mt-10'])
</div>
<p class="kicker mt-10 justify-center">{{ t('errors.notFoundKicker') }}</p>
<h1 class="mt-4 font-display text-h1 font-semibold text-heading">{{ t('errors.notFoundTitle') }} <em class="heading-accent">{{ t('errors.notFoundAccent') }}</em></h1>
<p class="mx-auto mt-5 max-w-md text-lead text-fg-muted">{{ t('errors.notFoundBody') }}</p>
<nav aria-label="{{ t('errors.helpfulLinks') }}" class="mt-8">
<ul class="flex flex-wrap justify-center gap-2">
@foreach ([['/', t('nav.home')], ['/projects', t('nav.projects')], ['/news', t('nav.news')], ['/events', t('nav.events')]] as [$nfHref, $nfLabel])
<li><a class="inline-flex min-h-11 items-center rounded-full bg-white px-5 font-semibold text-himalaya ring-1 ring-line-strong hover:bg-cream" href="{{ lurl($nfHref) }}">{{ $nfLabel }}</a></li>
@endforeach
</ul>
</nav>
<div class="mt-8">{{ \App\View\Ui::buttonLink('/donate', new \Illuminate\Support\HtmlString('<span class="inline-flex items-center gap-2">'.\App\View\Icon::svg('Heart', 'size-5').e(t('cta.donate')).'</span>'), size: 'lg') }}</div>
</x-site.container>
</section>
@endsection

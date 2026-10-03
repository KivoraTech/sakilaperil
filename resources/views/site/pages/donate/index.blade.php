{{-- app/[locale]/(site)/donate/page.tsx (DonateWidget + BankTransferPanel are ENGAGEMENT partials). --}}
@extends('layouts.site')

@section('content')
{{-- With bank transfer only, the page says so: no "secure payments" badge, no steps that do not exist, one bank panel. --}}
@php($badges = '<ul class="mt-6 flex flex-wrap gap-2">'.($onlinePayments ? '<li>'.view('site.ui.chip', ['tone' => 'white', 'slot' => new \Illuminate\Support\HtmlString(\App\View\Icon::svg('Lock', 'size-3.5').e(t('donatePage.badgeSecure')))])->render().'</li>' : '').($transparency ? '<li>'.view('site.ui.chip', ['tone' => 'white', 'slot' => new \Illuminate\Support\HtmlString(\App\View\Icon::svg('FileText', 'size-3.5').e(t('donatePage.badgeReports')))])->render().'</li>' : '').($onlinePayments ? '<li>'.view('site.ui.chip', ['tone' => 'white', 'slot' => new \Illuminate\Support\HtmlString(\App\View\Icon::svg('Landmark', 'size-3.5').e(t('donatePage.badgeBank')))])->render().'</li>' : '').'</ul>')
@partial('site.ui.page-header', ['kicker' => t('donatePage.kicker'), 'title' => t('donatePage.title'), 'lead' => t($onlinePayments ? 'donatePage.lead' : 'donatePage.leadBank'), 'children' => new \Illuminate\Support\HtmlString($badges)])
<x-site.section spacing="compact">
<x-site.container class="grid gap-8 lg:grid-cols-12">
<div class="lg:col-span-7">
<div class="overflow-hidden rounded-xl bg-white shadow-panel ring-1 ring-line/70">
<div class="prayer-flags h-[3px]!" aria-hidden="true"></div>
<div class="p-6 md:p-10">
@if ($project)
<p class="mb-6 rounded-md bg-sage-50 px-4 py-3 text-small text-sage-700">{{ t('donatePage.forProject') }} <strong class="font-semibold">{{ $project['title'] }}</strong></p>
@endif
<h2 class="sr-only">{{ t('donateBlock.widgetTitle') }}</h2>
@partialIf('engagement.donate-widget', ['locale' => $locale, 'source' => 'donate-page', 'projectId' => $project['id'] ?? null, 'defaultAmount' => $defaultAmount, 'showTitle' => false])
</div>
</div>
</div>
<aside class="lg:col-span-5" aria-labelledby="why-title">
<div class="surface-dark overflow-hidden rounded-xl lg:sticky lg:top-28">
@if (! empty($project['coverImage']))
<div class="relative h-44">{{ \App\View\Img::media($project['coverImage'], sizes: '(min-width: 1024px) 34vw, 100vw', alt: '') }}<div class="absolute inset-0 bg-gradient-to-t from-deep to-deep/0" aria-hidden="true"></div></div>
@endif
<div class="p-7 md:p-9">
<h2 id="why-title" class="font-display text-h3 font-semibold text-white">{{ t('donatePage.whyTitle') }}</h2>
<ul class="mt-5 space-y-4">
@foreach (['why1', $onlinePayments ? 'why2' : 'why2Bank', 'why3'] as $k)
<li class="flex gap-3"><span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-marigold text-deep">{!! \App\View\Icon::svg('Check', 'size-4', strokeWidth: 3) !!}</span><span>{{ t('donatePage.'.$k) }}</span></li>
@endforeach
</ul>
@if ($transparency)
{{ \App\View\Ui::buttonLink(\App\I18n\Routes::page($transparency['slug']), t('donatePage.readReports'), variant: 'ghost-light', size: 'sm', class: 'mt-8', arrow: true) }}
@endif
</div>
</div>
</aside>
</x-site.container>
</x-site.section>
@if ($onlinePayments)
<x-site.section surface="cream" spacing="compact" id="bonifico" labelled-by="bank-title">
<x-site.container size="content">
<div class="grid gap-8 md:grid-cols-12 md:items-start">
<div class="md:col-span-5">
<p class="kicker">{{ t('donatePage.bankKicker') }}</p>
<h2 id="bank-title" class="mt-3 font-display text-h2 font-semibold text-heading">{{ t('donatePage.bankTitle') }}</h2>
<p class="mt-3 text-fg-muted">{{ t('donatePage.bankBody') }}</p>
</div>
<div class="md:col-span-7">@partialIf('engagement.bank-transfer-panel', ['locale' => $locale])</div>
</div>
</x-site.container>
</x-site.section>
@endif
@endsection

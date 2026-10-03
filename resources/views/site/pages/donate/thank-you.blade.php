{{-- app/[locale]/(site)/donate/thank-you/page.tsx: "Postcard from Nepal". No confetti, no amounts from the URL. --}}
@extends('layouts.site')

@section('content')
<x-site.section spacing="compact" class="overflow-hidden">
<x-site.container size="content">
<article class="relative mx-auto grid max-w-4xl overflow-hidden rounded-xl bg-white shadow-panel ring-1 ring-line md:grid-cols-2">
<div class="relative min-h-64">
{{ \App\View\Img::media($photo, sizes: '(min-width: 768px) 32rem, 100vw', alt: '', priority: true) }}
<div class="absolute inset-0 bg-gradient-to-t from-deep/60 to-transparent" aria-hidden="true"></div>
<p class="absolute bottom-4 left-4 font-display text-lg text-white italic">{{ t('thanks.postmark') }}</p>
</div>
<div class="relative p-8 md:p-10">
<div class="absolute top-6 right-6 flex size-24 rotate-6 flex-col items-center justify-center rounded-md border-2 border-dashed border-sindoor/60 bg-sindoor-50 p-2 text-center text-sindoor-700" aria-hidden="true">
@partial('site.brand.logo-mark', ['class' => 'size-8'])
<span lang="ne" class="mt-1 text-sm font-semibold">धन्यवाद</span>
</div>
<p class="kicker">{{ t('thanks.kicker') }}</p>
<h1 class="mt-4 max-w-[14ch] font-display text-h1 font-semibold text-heading">{{ ! empty($donation['firstName']) ? t('thanks.titleNamed', ['name' => $donation['firstName']]) : t('thanks.title') }} <em class="heading-accent">{{ t('thanks.titleAccent') }}</em></h1>
@if ($ref)
@if (! $donation)
<p data-testid="donation-status" data-status="UNKNOWN" class="mt-5 rounded-md bg-cream px-4 py-3 text-small text-fg-muted">{{ t('donate.notFoundBody') }}</p>
@elseif ($donation['status'] === 'SUCCEEDED' || $donation['status'] === 'PENDING')
<p data-testid="donation-status" data-status="{{ $donation['status'] }}" class="mt-5 rounded-md bg-sage-50 px-4 py-3 text-small text-sage-700">{{ t($donation['frequency'] === 'monthly' ? 'thanks.summaryMonthly' : 'thanks.summaryOnce', ['amount' => \App\I18n\Format::money($donation['amount'], $donation['currency'], $locale)]) }}{{ $projectTitle ? ' '.t('thanks.summaryProject', ['project' => $projectTitle]) : '' }}{{ $donation['status'] === 'PENDING' ? ' '.t('thanks.pending') : '' }}{{ $donation['isTestMode'] ? ' '.t('thanks.testMode') : '' }}</p>
@elseif ($donation['status'] === 'FAILED' || $donation['status'] === 'CANCELED')
<p data-testid="donation-status" data-status="{{ $donation['status'] }}" class="mt-5 rounded-md bg-cream px-4 py-3 text-small text-fg-muted">{{ t('donate.cancelledBody') }}</p>
@endif
@endif
<p class="mt-5 text-fg-muted">{{ $custom !== '' ? $custom : t('thanks.body') }}</p>
<h2 class="mt-8 text-h4 font-semibold text-heading">{{ t('thanks.nextTitle') }}</h2>
<ol class="mt-3 list-decimal space-y-2 pl-5 text-fg-muted marker:font-semibold marker:text-marigold-700">
<li>{{ t('thanks.next1') }}</li>
<li>{{ t('thanks.next2') }}</li>
<li>{{ t('thanks.next3') }}</li>
</ol>
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'mt-8 text-line-strong/50'])
<p class="mt-2 text-right font-display text-lg text-himalaya italic">— {{ $settings['orgName'] }}</p>
</div>
</article>
<div class="mx-auto mt-12 grid max-w-4xl gap-6 md:grid-cols-2">
<div class="rounded-xl bg-cream p-7">
<h2 class="font-display text-h3 font-semibold text-heading">{{ t('thanks.newsletterTitle') }}</h2>
<p class="mt-2 text-small text-fg-muted">{{ t('thanks.newsletterBody') }}</p>
@partialIf('engagement.newsletter-form', ['locale' => $locale, 'source' => 'thank-you', 'variant' => 'stacked', 'class' => 'mt-4'])
</div>
<div class="surface-dark rounded-xl p-7">
<h2 class="font-display text-h3 font-semibold text-white">{{ t('thanks.seeTitle') }}</h2>
<p class="mt-2 text-small text-fg-muted">{{ t('thanks.seeBody') }}</p>
{{ \App\View\Ui::buttonLink($featured ? '/projects/'.$featured['slug'] : '/projects', $featured['title'] ?? t('cta.seeProjects'), variant: 'ghost-light', class: 'mt-6', arrow: true) }}
</div>
</div>
</x-site.container>
</x-site.section>
@endsection

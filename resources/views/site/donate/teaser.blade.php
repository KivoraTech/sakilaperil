{{--
    components/site/donate/donate-teaser.tsx: amount chips deep-link into /donate (presets + impact lines
    from SiteSettings.donation). Params: $locale, $project ({slug, title}|null), $heading.
--}}
@php($dtSettings = app(\App\Settings\SiteSettings::class)->get())
@php($dtDonation = $dtSettings['donation'] ?? [])
@php($dtCurrency = $dtDonation['currency'] ?? 'EUR')
@php($dtNote = function (int|float $amount) use ($dtDonation, $locale) { foreach ($dtDonation['impactNotes'] ?? [] as $n) { if ((float) $n['amount'] === (float) $amount) { return \App\I18n\Locales::pick($n['text'] ?? null, $locale); } } return ''; })
{{-- Amount chips deep-link to the online widget; with bank transfer only there is no amount step, so they are left out. --}}
@php($dtOnline = \App\Http\Controllers\Site\DonateController::onlinePaymentsAvailable())
<x-site.section spacing="compact">
<x-site.container>
<div class="surface-dark surface-himalaya relative isolate overflow-hidden rounded-xl p-8 md:p-12">
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'absolute right-0 bottom-3 -z-10 w-[40rem] max-w-full text-white/10'])
<div class="grid gap-8 lg:grid-cols-12 lg:items-center">
<div class="{{ $dtOnline ? 'lg:col-span-5' : 'lg:col-span-8' }}">
<p class="kicker">{{ t('donateTeaser.kicker') }}</p>
<h2 class="mt-3 font-display text-h2 font-semibold text-white">{{ $heading ?? (! empty($project) ? t('donateTeaser.projectHeading', ['project' => $project['title']]) : t('donateTeaser.heading')) }}</h2>
<p class="mt-3 text-fg-muted">{{ t('donateTeaser.body') }}</p>
</div>
@if ($dtOnline)
<ul class="grid grid-cols-2 gap-3 lg:col-span-7">
@foreach (array_slice($dtDonation['presetAmounts'] ?? [], 0, 4) as $dtI => $amount)
@php($dtN = $dtNote($amount))
<li data-reveal="true" style="--reveal-i:{{ $dtI }}"><a class="group flex h-full flex-col rounded-lg bg-white/8 p-4 ring-1 ring-white/20 transition-[color,background-color,transform,translate] duration-300 ease-out-soft hover:bg-white hover:text-himalaya motion:hover:-translate-y-1" href="{{ \App\View\Ui::href('/donate', ['amount' => $amount, 'project' => $project['slug'] ?? null]) }}"><span class="font-display text-[1.75rem] leading-none font-semibold text-white group-hover:text-himalaya">{{ \App\I18n\Format::wholeMoney($amount, $dtCurrency, $locale) }}</span>@if ($dtN !== '')<span class="mt-2 line-clamp-2 text-small text-fg-muted group-hover:text-ink-soft">{{ preg_replace('/^€?\s?\d+\s?€?\s?=\s?/u', '', $dtN) }}</span>@endif</a></li>
@endforeach
</ul>
@endif
</div>
<div class="mt-8"><a class="{{ \App\View\Ui::buttonClass('primary', 'lg') }}" href="{{ \App\View\Ui::href('/donate', ['project' => $project['slug'] ?? null]) }}">{!! \App\View\Icon::svg('Heart', 'size-5') !!}{{ t('cta.donate') }}</a></div>
</div>
</x-site.container>
</x-site.section>

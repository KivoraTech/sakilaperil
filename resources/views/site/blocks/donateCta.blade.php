{{--
    components/site/blocks/DonateCta.tsx: gift examples deep-link into /donate; with `showWidget` the
    DonateWidget (ENGAGEMENT: engagement.donate-widget) sits beside a dark "why give with us" panel.
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($settings = app(\App\Settings\SiteSettings::class)->get())
@php($demo = ! empty($settings['features']['demoContent']))
@php($side = ! empty($c['sideImage']) ? ($context['assets'][$c['sideImage']['assetId']] ?? null) : null)
@php($currency = $settings['donation']['currency'] ?? 'EUR')
@php($gifts = $c['gifts'] ?? [])
{{-- The amount in a gift link pre-selects it in the online widget; bank transfer has no amount step. --}}
@php($dcOnline = \App\Http\Controllers\Site\DonateController::onlinePaymentsAvailable())
@php($hasWhy = count($c['reasons'] ?? []) || count($c['trustStats'] ?? []) || $side)
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="donateCta">
<x-site.container>
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null, 'lead' => $c['lead'] ?? null])
@if (count($gifts))
<div class="mt-10">
<ul class="{{ \App\View\Html::cx('grid gap-4', count($gifts) >= 3 ? 'md:grid-cols-3' : 'md:grid-cols-2', count($gifts) === 4 ? 'lg:grid-cols-4' : null) }}">
@foreach ($gifts as $i => $g)
@php($gImg = ! empty($g['image']) ? ($context['assets'][$g['image']['assetId']] ?? null) : null)
@php($gAmount = \App\I18n\Format::wholeMoney($g['amount'], $currency, $loc))
<li data-reveal="true" style="--reveal-i:{{ $i }}">
<article class="group relative flex h-full items-stretch overflow-hidden rounded-lg bg-white shadow-card ring-1 ring-line/70 transition-[box-shadow,transform,translate] duration-500 ease-out-soft hover:shadow-raised md:flex-col motion:hover:-translate-y-1.5">
<div class="relative w-28 shrink-0 overflow-hidden bg-sand md:aspect-[16/10] md:w-full">
@if ($gImg)
{{ \App\View\Img::media($gImg, sizes: '(min-width: 768px) 30vw, 112px', alt: '', class: 'transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.08]') }}
@endif
</div>
<div class="flex flex-1 flex-col p-5">
<p class="font-display text-h2 leading-none font-semibold text-heading">{{ $gAmount }}</p>
<p class="mt-2 text-small text-fg-muted">{{ $g['description'] }}</p>
<a class="stretched-link mt-auto inline-flex items-center gap-1.5 pt-3 text-small font-semibold text-accent-text" href="{{ \App\View\Ui::href('/donate', ['amount' => $dcOnline ? $g['amount'] : null, 'project' => $c['projectId'] ?? null]) }}">{{ t('donateBlock.giveAmount', ['amount' => $gAmount]) }}<span aria-hidden="true" class="transition-transform duration-300 motion:group-hover:translate-x-1">→</span></a>
</div>
</article>
</li>
@endforeach
</ul>
</div>
@endif
@if (! empty($c['showWidget']))
{{-- The reference relies on tailwind-merge to drop lg:grid-cols-12 here; cx() does not merge, so emit only one. --}}
<div class="{{ \App\View\Html::cx('mt-10 grid gap-6 lg:gap-8', $hasWhy ? 'lg:grid-cols-12' : 'lg:grid-cols-1') }}">
<div class="{{ \App\View\Html::cx('order-1', $hasWhy ? 'lg:order-2 lg:col-span-7' : 'mx-auto w-full max-w-2xl') }}">
<div class="overflow-hidden rounded-xl bg-white shadow-panel ring-1 ring-line/70">
<div class="prayer-flags h-[3px]!" aria-hidden="true"></div>
<div class="p-6 md:p-9">
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
<h3 class="flex items-center gap-2 font-display text-h3 font-semibold text-himalaya">{!! \App\View\Icon::svg('Heart', 'size-6 text-sindoor') !!}{{ t('donateBlock.widgetTitle') }}</h3>
@if ($dcOnline)
@partial('site.ui.chip', ['tone' => 'sage', 'slot' => new \Illuminate\Support\HtmlString(\App\View\Icon::svg('Lock', 'size-3.5').e(t('donateBlock.secure')))])
@endif
</div>
@partialIf('engagement.donate-widget', ['locale' => $loc, 'source' => 'block:'.$block['id'], 'projectId' => $c['projectId'] ?? null, 'showTitle' => false])
</div>
</div>
</div>
@if ($hasWhy)
<div class="order-2 lg:order-1 lg:col-span-5">
<aside class="surface-dark relative flex flex-col overflow-hidden rounded-xl" aria-label="{{ t('donateBlock.whyTitle') }}">
@if ($side)
<div class="relative h-44 shrink-0">
{{ \App\View\Img::media($side, sizes: '(min-width: 1024px) 34vw, 100vw', alt: '', class: 'saturate-90') }}
<div class="absolute inset-0 bg-gradient-to-t from-deep to-deep/10" aria-hidden="true"></div>
</div>
@endif
<div class="flex flex-1 flex-col p-7 md:p-9">
<h3 class="font-display text-h3 font-semibold text-white">{{ t('donateBlock.whyTitle') }}</h3>
@if (count($c['reasons'] ?? []))
<ul class="mt-5 space-y-3">
@foreach ($c['reasons'] as $r)
<li class="flex gap-3"><span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-marigold text-deep">{!! \App\View\Icon::svg('Check', 'size-4', strokeWidth: 3) !!}</span><span>{{ $r }}</span></li>
@endforeach
</ul>
@endif
@if (count($c['trustStats'] ?? []))
<div class="mt-auto pt-8">
<dl class="grid grid-cols-3 gap-4 border-t border-white/15 pt-6">
@foreach ($c['trustStats'] as $st)
<div class="flex flex-col-reverse"><dt class="mt-1 text-small text-fg-muted">{{ $st['label'] }}</dt><dd class="font-display text-[1.75rem] leading-none font-semibold text-gold tabular-nums">{{ $st['value'] }}</dd></div>
@endforeach
</dl>
@if ($demo)
<p class="mt-3 text-[0.8125rem] text-mist">{{ t('impact.sampleNote') }}</p>
@endif
</div>
@endif
</div>
</aside>
</div>
@endif
</div>
@else
<div class="mt-10 flex flex-wrap items-center gap-4">{{ \App\View\Ui::buttonLink('/donate', t('cta.donate'), size: 'lg', arrow: true) }}</div>
@endif
</x-site.container>
</x-site.section>

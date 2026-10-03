{{-- components/site/blocks/Newsletter.tsx (form: ENGAGEMENT engagement.newsletter-form). --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($nlSurface = ['himalaya' => 'himalaya', 'deep' => 'deep', 'paper' => 'cream'][$s['tone']])
@php($nlDark = $s['tone'] !== 'paper')
<x-site.section :id="$s['anchor'] ?? null" spacing="compact" block="newsletter">
<x-site.container>
<div class="{{ \App\View\Html::cx('relative isolate grid gap-8 overflow-hidden rounded-xl p-8 md:p-12 lg:grid-cols-12 lg:items-center lg:gap-12 lg:p-14', $nlSurface === 'cream' ? 'surface-cream' : ($nlSurface === 'deep' ? 'surface-dark' : 'surface-dark surface-himalaya')) }}" data-reveal="true">
@partial('site.brand.ridge', ['variant' => 'line', 'class' => \App\View\Html::cx('absolute right-0 bottom-4 -z-10 w-[36rem] max-w-full', $nlDark ? 'text-white/10' : 'text-line-strong/30')])
<div class="lg:col-span-6">
<span class="{{ \App\View\Html::cx('flex size-12 items-center justify-center rounded-full', $nlDark ? 'bg-white/10 text-gold' : 'bg-white text-sindoor') }}">{!! \App\View\Icon::svg('Mail', 'size-6') !!}</span>
@if (! empty($c['kicker']))
<p class="kicker mt-6">{{ $c['kicker'] }}</p>
@endif
<h2 class="mt-3 font-display text-h2 font-semibold text-heading">{{ $c['heading'] }}@if (! empty($c['headingAccent'])) <em class="heading-accent">{{ $c['headingAccent'] }}</em>@endif</h2>
@if (! empty($c['body']))
<p class="mt-4 text-lead text-fg-muted">{{ \App\View\Ui::todoText($c['body']) }}</p>
@endif
</div>
<div class="lg:col-span-6">
@partialIf('engagement.newsletter-form', ['locale' => $context['locale'], 'source' => 'block:'.$block['id'], 'variant' => 'stacked', 'tone' => $nlDark ? 'dark' : 'light', 'showTitle' => false])
@if (! empty($c['note']))
<p class="mt-3 text-small text-fg-muted">{{ $c['note'] }}</p>
@endif
</div>
</div>
</x-site.container>
</x-site.section>

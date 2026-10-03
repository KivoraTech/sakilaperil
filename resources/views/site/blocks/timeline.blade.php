{{-- components/site/blocks/Timeline.tsx: ordered list with a centre line that "draws itself" (CSS). --}}
@php($c = $block['content'])
@php($s = $block['settings'])
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="timeline">
<x-site.container size="content">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null, 'align' => 'center'])
<div class="relative mt-14">
<div class="absolute top-0 bottom-0 left-[1.1875rem] w-0.5 bg-line md:left-1/2 md:-translate-x-1/2" aria-hidden="true"><div class="timeline-line-fill h-full w-full bg-gradient-to-b from-marigold to-sindoor"></div></div>
<ol class="space-y-12 md:space-y-16">
@foreach ($c['items'] as $i => $item)
@php($tImg = ! empty($item['image']) ? ($context['assets'][$item['image']['assetId']] ?? null) : null)
@php($right = $i % 2 === 1)
<li class="relative grid gap-5 pl-14 md:grid-cols-2 md:gap-16 md:pl-0" data-reveal="{{ $right ? 'slide-right' : 'slide-left' }}">
<span class="absolute top-1 left-0 flex size-10 items-center justify-center rounded-full bg-bg ring-2 ring-marigold md:left-1/2 md:-translate-x-1/2" aria-hidden="true"><span class="reveal-pop size-3 rounded-full bg-sindoor"></span></span>
<div class="{{ $right ? 'md:order-2 md:text-left' : 'md:text-right' }}">
<p class="reveal-rise font-display text-h2 leading-none font-semibold text-accent-text">{{ $item['year'] }}</p>
<h3 class="mt-3 font-display text-h3 font-semibold text-heading">{{ $item['title'] }}</h3>
@if (! empty($item['body']))
<p class="{{ \App\View\Html::cx('mt-2 text-fg-muted md:max-w-sm', ! $right ? 'md:ml-auto' : null) }}">{{ \App\View\Ui::todoText($item['body']) }}</p>
@endif
</div>
@if ($tImg)
<div class="{{ \App\View\Html::cx('relative aspect-[4/3] overflow-hidden rounded-lg bg-sand shadow-card', $right ? 'md:order-1' : null) }}">{{ \App\View\Img::media($tImg, sizes: '(min-width: 768px) 30rem, 90vw', alt: $item['image']['alt'] ?? null) }}</div>
@else
<div class="hidden md:block"></div>
@endif
</li>
@endforeach
</ol>
</div>
</x-site.container>
</x-site.section>

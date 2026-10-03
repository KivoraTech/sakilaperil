{{-- components/site/blocks/Team.tsx --}}
@php($c = $block['content'])
@php($s = $block['settings'])
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="team">
<x-site.container>
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null])
@if (! empty($c['lead']))
<p class="mt-5 max-w-[36rem] text-lead text-fg-muted">{{ \App\View\Ui::todoText($c['lead']) }}</p>
@endif
@if (count($c['members'] ?? []))
<ul class="mt-12 grid grid-cols-2 gap-x-5 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
@foreach ($c['members'] as $i => $m)
@php($mImg = ! empty($m['image']) ? ($context['assets'][$m['image']['assetId']] ?? null) : null)
<li data-reveal="true" style="--reveal-i:{{ $i % 4 }}">
<div class="relative aspect-[4/5] overflow-hidden rounded-lg bg-cream">
@if ($mImg)
{{ \App\View\Img::media($mImg, sizes: '(min-width: 1024px) 22vw, 45vw', alt: $m['image']['alt'] ?? $m['name']) }}
@else
<span class="absolute inset-0 flex items-center justify-center font-display text-5xl font-semibold text-himalaya/70" aria-hidden="true">{{ \App\View\Cards::initials($m['name']) }}</span>
@endif
</div>
<h3 class="mt-4 font-display text-xl font-semibold text-heading">{{ $m['name'] }}</h3>
@if (! empty($m['role']))
<p class="text-small font-medium text-accent-text">{{ $m['role'] }}</p>
@endif
@if (! empty($m['bio']))
<p class="mt-2 text-small text-fg-muted">{{ $m['bio'] }}</p>
@endif
</li>
@endforeach
</ul>
@endif
</x-site.container>
</x-site.section>

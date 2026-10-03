{{-- components/site/blocks/Partners.tsx: logos (grayscale → colour on hover) or name tiles. --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($cls = 'group flex min-h-28 items-center justify-center rounded-lg bg-white p-5 text-center shadow-card ring-1 ring-line/70 transition-shadow hover:shadow-raised')
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" spacing="compact" block="partners">
<x-site.container class="grid gap-10 lg:grid-cols-12 lg:items-center">
<div class="lg:col-span-5">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'lead' => $c['body'] ?? null, 'size' => 'h3'])
@if (! empty($c['cta']))
{{ \App\View\Ui::buttonLink($c['cta']['href'], $c['cta']['label'], variant: 'secondary', class: 'mt-8', arrow: true) }}
@endif
</div>
@if (count($c['partners'] ?? []))
<ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:col-span-7">
@foreach ($c['partners'] as $i => $p)
@php($logo = ! empty($p['logo']) ? ($context['assets'][$p['logo']['assetId']] ?? null) : null)
@php($inner = $logo ? '<span class="relative block h-16 w-full">'.\App\View\Img::media($logo, sizes: '200px', alt: $p['name'], class: 'object-contain! grayscale transition duration-300 group-hover:grayscale-0')->toHtml().'</span>' : '<span class="font-display text-lg leading-snug font-semibold text-himalaya">'.e($p['name']).'</span>')
<li data-reveal="true" style="--reveal-i:{{ $i % 4 }}">
@if (! empty($p['url']))
<a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $cls }}">{!! $inner !!}<span class="sr-only"> ({{ $p['name'] }})</span></a>
@else
<div class="{{ $cls }}">{!! $inner !!}</div>
@endif
</li>
@endforeach
</ul>
@endif
</x-site.container>
</x-site.section>

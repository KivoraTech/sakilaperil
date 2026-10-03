{{-- components/site/blocks/Testimonials.tsx: all items visible (no auto-rotating carousel, WCAG 2.2.2). --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($single = count($c['items']) === 1)
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="testimonials">
<x-site.container>
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'align' => 'center', 'class' => 'mb-12'])
<ul class="{{ \App\View\Html::cx('grid gap-6', $single ? 'mx-auto max-w-3xl' : 'md:grid-cols-2 lg:grid-cols-3') }}">
@foreach ($c['items'] as $i => $item)
@php($img = ! empty($item['image']) ? ($context['assets'][$item['image']['assetId']] ?? null) : null)
<li data-reveal="true" style="--reveal-i:{{ $i }}">
<figure class="{{ \App\View\Html::cx('flex h-full flex-col rounded-lg bg-surface p-7 shadow-card ring-1 ring-line/70 md:p-8', $single ? 'items-center text-center' : null) }}">
{!! \App\View\Icon::svg('Quote', 'size-8 text-marigold') !!}
<blockquote class="{{ \App\View\Html::cx('mt-4 flex-1 font-display text-heading', $single ? 'text-statement' : 'text-[1.25rem] leading-relaxed') }}"><p>“{{ $item['quote'] }}”</p></blockquote>
<figcaption class="mt-6 flex items-center gap-3 border-t border-line pt-5">
<span class="relative flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-cream font-display text-lg font-semibold text-himalaya">
@if ($img)
{{ \App\View\Img::media($img, sizes: '44px', alt: '') }}
@else
<span aria-hidden="true">{{ mb_substr($item['name'], 0, 1) }}</span>
@endif
</span>
<span class="text-left">
<span class="block font-semibold text-fg">{{ $item['name'] }}</span>
@if (! empty($item['role']))
<span class="block text-small text-fg-muted">{{ $item['role'] }}</span>
@endif
</span>
</figcaption>
</figure>
</li>
@endforeach
</ul>
</x-site.container>
</x-site.section>

{{-- components/site/blocks/Spacer.tsx --}}
@php($s = $block['settings'])
<div{{ \App\View\Html::attrs(['id' => $s['anchor'] ?? null, 'data-block' => 'spacer', 'aria-hidden' => 'true', 'class' => \App\View\Html::cx('flex items-center', ['sm' => 'h-8', 'md' => 'h-16', 'lg' => 'h-24', 'xl' => 'h-36'][$s['size']], \App\View\Ui::SURFACE_CLASS[\App\View\Ui::surfaceOf($s['background'] ?? null)])]) }}>
@if (! empty($s['divider']))
<div class="mx-auto w-full max-w-content px-gutter">@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'mx-auto max-w-md text-line-strong/60'])</div>
@endif
</div>

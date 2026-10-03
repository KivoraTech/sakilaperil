{{--
    components/site/blocks/ImpactStats.tsx: "every number has a source". SSR prints the true value;
    the count-up (resources/js/site/count-up.ts) only animates from 0 when scrolled into view.
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($loc = $context['locale'])
@php($settings = app(\App\Settings\SiteSettings::class)->get())
@php($transparency = \App\Queries\Public\Pages::byKey($loc, 'transparency'))
@php($items = ($c['source'] ?? null) === 'settings' ? array_map(fn ($i) => ['value' => $i['value'], 'suffix' => $i['suffix'] ?? null, 'label' => \App\I18n\Locales::pick($i['label'] ?? null, $loc)], $settings['impactStats'] ?? []) : array_map(fn ($i) => ['value' => $i['value'], 'suffix' => $i['suffix'] ?? null, 'label' => $i['label']], $c['items'] ?? []))
@if (count($items))
@php($demo = ! empty($settings['features']['demoContent']))
@php($surface = \App\View\Ui::surfaceOf($s['background'] ?? null))
@php($dark = \App\View\Ui::isDark($surface))
<x-site.section :id="$s['anchor'] ?? null" :surface="$surface" spacing="compact" block="impactStats" :labelled-by="$block['id'].'-h'">
<x-site.container>
<div class="flex flex-wrap items-center justify-between gap-4">
<h2 id="{{ $block['id'] }}-h" class="{{ ! empty($c['heading']) ? 'font-display text-h3 font-semibold text-heading' : 'sr-only' }}">{{ ! empty($c['heading']) ? $c['heading'] : t('impact.title') }}</h2>
@if ($demo)
@partial('site.ui.chip', ['tone' => $dark ? 'dark' : 'warning', 'class' => $dark ? 'text-gold! ring-1 ring-gold/50' : '', 'slot' => t('impact.sample')])
@endif
</div>
<dl class="{{ \App\View\Html::cx('mt-6 grid grid-cols-2 gap-x-6 gap-y-10 md:gap-x-10', count($items) >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3') }}">
@foreach ($items as $i => $item)
<div class="flex flex-col-reverse" data-reveal="true" style="--reveal-i:{{ $i }}">
<dt class="mt-3 max-w-[16rem] text-small text-fg-muted">{{ $item['label'] }}</dt>
<dd class="border-t-2 border-marigold pt-5">
<span class="font-display text-stat font-semibold text-heading tabular-nums">
<span class="sr-only">{{ \App\I18n\Format::number($item['value'], $loc) }}{{ $item['suffix'] ?? '' }}</span>
<span aria-hidden="true" data-count-up="{{ \App\View\Html::num($item['value']) }}" data-count-delay="{{ $i * 120 }}">{{ \App\I18n\Format::number($item['value'], $loc) }}</span>
@if (! empty($item['suffix']))
<span aria-hidden="true" class="text-accent-text">{{ $item['suffix'] }}</span>
@endif
</span>
</dd>
</div>
@endforeach
</dl>
@if ($transparency)
<div class="mt-10 flex flex-wrap items-center gap-x-3 gap-y-2 text-small text-fg-muted">
{!! \App\View\Icon::svg('FileText', 'size-4 text-accent-text') !!}
<span>{{ t('impact.sourceNote') }}</span>
{{ \App\View\Ui::buttonLink(\App\I18n\Routes::page($transparency['slug']), t('impact.sourceLink'), variant: 'quiet', size: 'sm', arrow: true) }}
</div>
@endif
</x-site.container>
</x-site.section>
@endif

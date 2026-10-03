{{-- components/site/blocks/Faq.tsx: native <details>/<summary> accordion + FAQPage JSON-LD. --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($ld = array_values(array_filter($c['items'], fn ($i) => ! str_contains($i['answer'], '[[TODO'))))
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="faq">
<x-site.container size="content" class="grid gap-10 lg:grid-cols-12">
<div class="lg:col-span-4">@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'lead' => $c['lead'] ?? null, 'size' => 'h2'])</div>
<div class="divide-y divide-line border-y border-line lg:col-span-8">
@foreach ($c['items'] as $item)
<details class="group">
<summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-6 py-5 text-h4 font-semibold text-heading [&::-webkit-details-marker]:hidden">{{ $item['question'] }}<span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-cream text-himalaya transition-transform group-open:rotate-45">{!! \App\View\Icon::svg('Plus', 'size-5') !!}</span></summary>
<div class="space-y-3 pr-12 pb-6 text-fg-muted">
@foreach (preg_split('/\n{2,}/', $item['answer']) as $p)
<p>{{ \App\View\Ui::todoText($p) }}</p>
@endforeach
</div>
</details>
@endforeach
</div>
</x-site.container>
@if (count($ld))
{{ \App\View\Html::jsonLd(\App\View\Seo::faqJsonLd($ld)) }}
@endif
</x-site.section>

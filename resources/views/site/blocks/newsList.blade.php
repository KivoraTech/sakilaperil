{{-- components/site/blocks/NewsList.tsx --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($news = \App\Queries\Public\News::list($context['locale'], ['limit' => (int) $c['limit'], 'categoryId' => $c['categoryId'] ?? null]))
@if (count($news['items']))
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="newsList">
<x-site.container>
<div class="flex flex-wrap items-end justify-between gap-6">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null])
@if (! empty($c['showAllLink']))
{{ \App\View\Ui::buttonLink('/news', t('cta.allNews'), variant: 'secondary', arrow: true) }}
@endif
</div>
<ul class="mt-10 grid gap-x-6 gap-y-12 {{ \App\View\Ui::cardGrid(count($news['items'])) }}">
@foreach ($news['items'] as $i => $post)
<li data-reveal="true" style="--reveal-i:{{ $i % 3 }}">@partial('site.cards.news', ['post' => $post, 'locale' => $context['locale']])</li>
@endforeach
</ul>
</x-site.container>
</x-site.section>
@endif

{{--
    components/site/ui/page-header.tsx: cream band, kicker, H1 (+ accent), lead, ridge line.
    Params: $kicker, $title, $accent, $lead, $breadcrumbs (HtmlString), $children (HtmlString), $class.
--}}
<header class="{{ \App\View\Html::cx('surface-cream relative overflow-hidden pt-8 pb-14 md:pt-10 md:pb-20', $class ?? null) }}">
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'pointer-events-none absolute right-[-4rem] bottom-2 w-[48rem] max-w-none text-line-strong/25'])
<x-site.container class="relative">
@if (! empty($breadcrumbs))
<div class="mb-8">{{ $breadcrumbs }}</div>
@endif
@if (! empty($kicker))
<p class="kicker">{{ $kicker }}</p>
@endif
<h1 class="{{ \App\View\Html::cx('max-w-4xl font-display text-h1 font-semibold text-heading', ! empty($kicker) ? 'mt-4' : null) }}">{{ $title }}@if (! empty($accent)) <em class="heading-accent">{{ $accent }}</em>@endif</h1>
@if (! empty($lead))
<p class="mt-5 max-w-[40rem] text-lead text-fg-muted">{{ $lead }}</p>
@endif
{{ $children ?? '' }}
</x-site.container>
</header>

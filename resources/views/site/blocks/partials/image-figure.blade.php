<figure data-reveal="true">
<div class="{{ \App\View\Html::cx('relative overflow-hidden bg-sand', $full ? 'aspect-[21/9] max-h-[80svh] w-full' : 'rounded-lg') }}">
{{ \App\View\Img::media($asset, sizes: $full ? '100vw' : ($s['width'] === 'wide' ? '(min-width: 1280px) 76rem, 100vw' : '(min-width: 1024px) 64rem, 100vw'), alt: $c['image']['alt'] ?? null, fill: $full, priority: $context['index'] === 0) }}
</div>
@if (! empty($c['caption']))
<figcaption class="{{ \App\View\Html::cx('mt-3 text-small text-fg-muted', $full ? 'mx-auto max-w-wide px-gutter' : null) }}">{{ \App\View\Ui::todoText($c['caption']) }}</figcaption>
@endif
</figure>

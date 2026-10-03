<div class="surface-dark relative flex flex-col justify-end overflow-hidden rounded-lg p-8">
<svg aria-hidden="true" viewBox="0 0 200 100" class="absolute top-6 right-0 w-48 text-white/10" fill="currentColor"><path d="M0 100 L50 40 L80 70 L120 20 L170 80 L200 60 L200 100 Z"></path></svg>
<h3 class="font-display text-h3 font-semibold text-white">{{ $card['heading'] }}</h3>
@if (! empty($card['body']))
<p class="mt-3 text-fg-muted">{{ \App\View\Ui::todoText($card['body']) }}</p>
@endif
{{ \App\View\Ui::buttonLink($card['cta']['href'], $card['cta']['label'], class: 'mt-6 self-start', arrow: true) }}
</div>

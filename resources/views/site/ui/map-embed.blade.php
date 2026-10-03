{{--
    components/site/ui/map-embed.tsx: privacy-friendly map — nothing third-party loads until the visitor
    clicks "Show map" (resources/js/site/map-embed.ts). Params: $embedUrl, $openUrl, $label.
--}}
<div class="relative aspect-[4/3] overflow-hidden rounded-lg bg-cream ring-1 ring-line sm:aspect-[16/9] lg:aspect-[4/3]" data-map-embed="{{ $embedUrl }}" data-map-title="{{ $label }}">
<div class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center">
@partial('site.brand.ridge', ['variant' => 'line', 'class' => 'absolute inset-x-0 bottom-6 text-line-strong/40'])
<span class="flex size-14 items-center justify-center rounded-full bg-white text-sindoor shadow-card">{!! \App\View\Icon::svg('MapPin', 'size-7') !!}</span>
<p class="max-w-xs text-small text-ink-soft">{{ t('contactBlock.mapConsent') }}</p>
<div class="flex flex-wrap justify-center gap-3">
<button type="button" class="inline-flex min-h-11 items-center rounded-full bg-himalaya px-5 font-semibold text-white hover:bg-himalaya-600" data-map-load>{{ t('contactBlock.showMap') }}</button>
<a href="{{ $openUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-full px-5 font-semibold text-himalaya ring-[1.5px] ring-himalaya ring-inset hover:bg-himalaya hover:text-white">{{ t('contactBlock.openMap') }}<span class="sr-only"> ({{ t('contactBlock.newTab') }})</span></a>
</div>
</div>
</div>

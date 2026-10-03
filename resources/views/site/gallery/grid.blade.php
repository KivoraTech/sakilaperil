{{--
    components/site/gallery/gallery-grid.tsx: masonry/grid gallery + accessible lightbox (native
    <dialog>, built client-side by resources/js/site/gallery.ts from the JSON below).
    Params: $items (GalleryEntry list), $layout ('masonry'|'grid'), $wide, $syncUrl.
--}}
@php($ggLayout = $layout ?? 'masonry')
@php($ggId = 'gallery-'.substr(md5(json_encode($items)), 0, 8))
<ul class="{{ $ggLayout === 'masonry' ? \App\View\Html::cx('masonry', ! empty($wide) ? 'masonry-4' : null) : 'grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4' }}" data-gallery="{{ $ggId }}">
@foreach ($items as $i => $item)
<li>
<button data-reveal="wipe-up" style="--reveal-i:{{ $i % 4 }}" type="button" data-gallery-open="{{ $i }}" class="{{ \App\View\Html::cx('group relative block w-full overflow-hidden rounded-md bg-sand', $ggLayout === 'grid' ? 'aspect-square' : null) }}" aria-label="{{ $item['type'] === 'PHOTO' ? t('gallery.openPhoto', ['alt' => $item['image']['alt'] ?: ($item['caption'] ?: (string) ($i + 1))]) : t('gallery.openVideo', ['title' => $item['caption'] ?: (string) ($i + 1)]) }}">
@if ($item['type'] === 'PHOTO')
@if ($ggLayout === 'grid' || empty($item['image']['width']) || empty($item['image']['height']))
{{ \App\View\Img::image(src: $item['image']['url'], alt: (string) ($item['image']['alt'] ?? ''), sizes: '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw', fill: true, eager: $i < 4, class: 'object-cover transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.06]', objectPosition: \App\View\Gallery::focal($item['image'])) }}
@else
{{ \App\View\Img::image(src: $item['image']['url'], alt: (string) ($item['image']['alt'] ?? ''), sizes: '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw', width: $item['image']['width'], height: $item['image']['height'], eager: $i < 4, class: 'h-auto w-full transition-transform duration-1000 ease-out-soft motion:group-hover:scale-[1.06]') }}
@endif
@else
<span class="flex aspect-video w-full flex-col items-center justify-center gap-3 bg-himalaya p-4 text-center text-white">
<span class="flex size-14 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/40">{!! \App\View\Icon::svg('Play', 'size-6 translate-x-0.5') !!}</span>
@if ($item['caption'] !== '')
<span class="line-clamp-2 text-small">{{ $item['caption'] }}</span>
@endif
</span>
@endif
<span class="pointer-events-none absolute inset-0 bg-deep/0 transition-colors group-hover:bg-deep/10" aria-hidden="true"></span>
@if ($item['type'] === 'PHOTO' && $item['caption'] !== '')
<span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-deep/80 to-transparent p-3 pt-10 text-left text-small text-white opacity-0 transition-[opacity,transform,translate] duration-500 ease-out-soft group-hover:opacity-100 group-focus-visible:opacity-100 motion:translate-y-2 motion:group-hover:translate-y-0" aria-hidden="true"><span class="line-clamp-2">{{ $item['caption'] }}</span></span>
@endif
</button>
</li>
@endforeach
</ul>
<dialog class="lightbox m-0 h-dvh max-h-none w-screen max-w-none bg-transparent p-0 text-white motion:open:animate-fade-in" aria-label="{{ t('gallery.lightbox') }}" data-gallery-dialog="{{ $ggId }}"></dialog>
<script type="application/json" data-gallery-data="{{ $ggId }}">{!! \App\View\Gallery::lightboxJson($items, ! empty($syncUrl)) !!}</script>

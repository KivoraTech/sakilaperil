{{-- components/site/blocks/Gallery.tsx --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($gItems = [])
@php($moreHref = '/gallery')
@if (($c['source'] ?? null) === 'album' && ! empty($c['albumId']))
@php($album = \App\Queries\Public\Albums::get($context['locale'], ['id' => $c['albumId']]))
@if ($album)
@php($gItems = $album['items'])
@php($moreHref = \App\I18n\Routes::album($album['slug']))
@endif
@else
@foreach ($c['images'] ?? [] as $gi => $img)
@if (isset($context['assets'][$img['assetId']]))
@php($a = $context['assets'][$img['assetId']])
@php($gItems[] = ['id' => $block['id'].'-'.$gi, 'type' => 'PHOTO', 'image' => [...$a, 'alt' => $img['alt'] ?? $a['alt']], 'caption' => $img['caption'] ?? ''])
@endif
@endforeach
@endif
@php($gItems = array_slice($gItems, 0, (int) $c['limit']))
@if (count($gItems))
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" block="gallery">
<x-site.container size="bleed">
<div class="flex flex-wrap items-end justify-between gap-6">
@partial('site.ui.section-intro', ['kicker' => $c['kicker'] ?? null, 'heading' => $c['heading'] ?? null, 'accent' => $c['headingAccent'] ?? null, 'lead' => $c['lead'] ?? null])
{{ \App\View\Ui::buttonLink($moreHref, t('gallery.viewAll'), variant: 'secondary', arrow: true) }}
</div>
<div class="mt-10">@partial('site.gallery.grid', ['items' => $gItems, 'layout' => $s['layout'], 'wide' => true])</div>
</x-site.container>
</x-site.section>
@endif

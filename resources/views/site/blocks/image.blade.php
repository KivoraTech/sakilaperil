{{-- components/site/blocks/Image.tsx --}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($asset = $context['assets'][$c['image']['assetId']] ?? null)
@php($full = $s['width'] === 'full')
@php($figure = view('site.blocks.partials.image-figure', ['c' => $c, 's' => $s, 'asset' => $asset, 'full' => $full, 'context' => $context])->render())
<x-site.section :id="$s['anchor'] ?? null" :surface="\App\View\Ui::surfaceOf($s['background'] ?? null)" :spacing="$full ? 'none' : 'compact'" block="image">
@if ($full)
{!! $figure !!}
@else
<x-site.container :size="$s['width'] === 'wide' ? 'wide' : 'content'">{!! $figure !!}</x-site.container>
@endif
</x-site.section>

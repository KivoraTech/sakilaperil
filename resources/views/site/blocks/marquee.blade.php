{{--
    components/site/blocks/Marquee.tsx: scrolling words strip. The first copy is the readable list; extra
    copies are aria-hidden for a seamless loop. Moves only with motion on and on screen; pause button
    (resources/js/site/marquee.ts, WCAG 2.2.2).
--}}
@php($c = $block['content'])
@php($s = $block['settings'])
@php($mqItems = array_map(fn ($i) => $i['text'], $c['items']))
@php($mqCopies = 1 + (int) ceil(8 / count($mqItems)))
@php($mqSurface = \App\View\Ui::surfaceOf($s['background'] ?? null))
@php($mqDuration = \App\View\Html::num(count($mqItems) * ['slow' => 7, 'normal' => 5, 'fast' => 3.5][$s['speed']]))
<section{{ \App\View\Html::attrs(['id' => $s['anchor'] ?? null, 'data-block' => 'marquee', 'class' => \App\View\Html::cx('marquee relative overflow-hidden py-6 md:py-8', $mqSurface === 'deep' || $mqSurface === 'himalaya' ? 'border-t border-white/8' : null, \App\View\Ui::SURFACE_CLASS[$mqSurface])]) }}>
<div data-loop="true" class="overflow-hidden">
<div class="marquee-track font-display text-[clamp(2rem,5vw,3.6rem)] leading-none font-light whitespace-nowrap text-heading" style="--copies:{{ $mqCopies }};--marquee-duration:{{ $mqDuration }}s">
@for ($k = 0; $k < $mqCopies; $k++)
<ul class="marquee-copy flex shrink-0 items-center gap-x-12 gap-y-4 px-gutter motion:px-0 motion:pr-12"@if ($k > 0) aria-hidden="true"@endif>
@foreach ($mqItems as $mi => $w)
<li class="flex items-center gap-12"><span{!! ($s['style'] ?? null) === 'alternate' && $mi % 2 === 1 ? ' class="text-outline italic"' : '' !!}>{{ $w }}</span><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-[0.5em] shrink-0 text-marigold"><path d="M12 2l2.4 7.6H22l-6.2 4.5 2.4 7.6L12 17.2l-6.2 4.5 2.4-7.6L2 9.6h7.6z"></path></svg></li>
@endforeach
</ul>
@endfor
</div>
</div>
<button type="button" class="marquee-pause absolute right-3 bottom-3 size-11 items-center justify-center rounded-full bg-black/25 text-current ring-1 ring-current/30 backdrop-blur-sm hover:bg-black/40" aria-pressed="false" data-marquee-pause data-label-pause="{{ t('marquee.pause') }}" data-label-play="{{ t('marquee.play') }}">{!! \App\View\Icon::svg('Pause', 'size-4') !!}<span class="sr-only">{{ t('marquee.pause') }}</span><template data-icon-pause>{!! \App\View\Icon::svg('Pause', 'size-4') !!}</template><template data-icon-play>{!! \App\View\Icon::svg('Play', 'size-4') !!}</template></button>
</section>

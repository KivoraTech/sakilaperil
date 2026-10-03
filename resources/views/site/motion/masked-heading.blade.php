{{-- components/site/motion/split-text.tsx MaskedHeading. Params: $heading, $accent. --}}
@php($mhMain = \App\View\Motion::words($heading))
@php($mhExtra = \App\View\Motion::words($accent ?? null))
<span class="sr-only">{{ $heading }}{{ ! empty($accent) ? ' '.$accent : '' }}</span>
<span aria-hidden="true">
@foreach ($mhMain as $mhI => $mhW)
<span class="word-mask"><span style="--wi:{{ $mhI }}">{{ $mhW }}</span></span>&#32;
@endforeach
@if (count($mhExtra))
<em class="heading-accent">
@foreach ($mhExtra as $mhJ => $mhW)
<span class="word-mask"><span style="--wi:{{ count($mhMain) + $mhJ }}">{{ $mhW }}</span></span>&#32;
@endforeach
</em>
@endif
</span>

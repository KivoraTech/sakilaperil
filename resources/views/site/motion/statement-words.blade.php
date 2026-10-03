{{-- components/site/motion/split-text.tsx StatementWords. Params: $text, $accent. --}}
@php($swMain = \App\View\Motion::words($text))
@php($swExtra = \App\View\Motion::words($accent ?? null))
@php($swTotal = max(1, count($swMain) + count($swExtra) - 1))
@foreach ($swMain as $swI => $swW)
<span class="stmt-w" style="--wi:{{ $swI }};--wp:{{ \App\View\Motion::wordProgress($swI, $swTotal) }}">{{ $swW }}</span>&#32;
@endforeach
@if (count($swExtra))
<em class="heading-accent">
@foreach ($swExtra as $swJ => $swW)
<span class="stmt-w" style="--wi:{{ count($swMain) + $swJ }};--wp:{{ \App\View\Motion::wordProgress(count($swMain) + $swJ, $swTotal) }}">{{ $swW }}</span>&#32;
@endforeach
</em>
@endif

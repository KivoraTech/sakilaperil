{{-- components/site/brand/prayer-flags.tsx PrayerFlagGarland (home hero). Params: $class. --}}
<svg viewBox="0 0 1440 160" preserveAspectRatio="none" aria-hidden="true" focusable="false" data-loop="true" class="{{ \App\View\Html::cx('flag-garland pointer-events-none', $class ?? null) }}">
<path d="M0 30 Q360 120 720 60 T1440 90" fill="none" stroke="rgb(255 255 255 / 0.45)" stroke-width="1.2"></path>
@foreach (\App\View\Brand::garlandFlags() as $flag)
<path class="flag" d="{{ $flag['d'] }}" fill="{{ $flag['fill'] }}" opacity="0.88" style="--delay:{{ $flag['delay'] }};--dur:{{ $flag['dur'] }}"></path>
@endforeach
</svg>

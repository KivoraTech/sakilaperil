{{-- components/site/brand/ridge.tsx Ridge. Params: $variant ('fill'|'line'), $class. Decorative. --}}
@php($ridgeLine = 'M0 62 L38 50 L64 56 L96 30 L118 42 L146 14 L170 34 L188 26 L214 48 L246 38 L272 52 L300 22 L322 36 L344 8 L372 40 L398 30 L426 50 L458 44 L486 58 L520 36 L544 46 L572 20 L598 40 L630 52 L664 46 L700 60')
{{-- Html::cx() does not merge like tailwind-merge: a caller's own width must REPLACE w-full, not sit beside it. --}}
@php($ridgeHasWidth = preg_match('/(^|\s)w-/', (string) ($class ?? '')) === 1)
@if (($variant ?? 'fill') === 'line')
<svg viewBox="0 0 700 70" aria-hidden="true" data-reveal="draw" class="{{ \App\View\Html::cx('block h-auto', $ridgeHasWidth ? null : 'w-full', $class ?? null) }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"><path d="{{ $ridgeLine }}" vector-effect="non-scaling-stroke"></path></svg>
@else
<svg viewBox="0 0 700 70" preserveAspectRatio="none" aria-hidden="true" class="{{ \App\View\Html::cx('block h-8 w-full md:h-12', $class ?? null) }}" fill="currentColor"><path d="{{ $ridgeLine }} L700 70 L0 70 Z"></path></svg>
@endif

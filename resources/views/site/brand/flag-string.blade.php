{{-- ridge.tsx FlagString: small string of prayer flags for the 404 illustration. Params: $class. --}}
@php($fsColors = ['#2F6FB5', '#F5F2EA', '#C8372D', '#3C8D4E', '#F2C230'])
<svg viewBox="0 0 220 60" aria-hidden="true"@if (! empty($class)) class="{{ $class }}"@endif>
<path d="M4 10 Q110 44 216 10" fill="none" stroke="#8C8272" stroke-width="1.2"></path>
@for ($i = 0; $i < 10; $i++)
@php($fsX = 14 + $i * 20.5)
@php($fsT = $fsX / 220)
@php($fsY = 10 + 34 * 4 * $fsT * (1 - $fsT) * 0.98)
<rect x="{{ \App\View\Html::num($fsX - 6) }}" y="{{ \App\View\Html::num($fsY) }}" width="12" height="15" rx="1" fill="{{ $fsColors[$i % 5] }}" stroke="#8C8272" stroke-width="0.6"></rect>
@endfor
</svg>

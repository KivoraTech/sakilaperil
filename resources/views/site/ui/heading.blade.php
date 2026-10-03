{{-- components/site/ui/heading.tsx Heading. Params: $as, $size, $text, $accent, $id, $class. --}}
@php($hSizes = ['display' => 'text-display font-medium', 'h1' => 'text-h1 font-semibold', 'h2' => 'text-h2 font-semibold', 'h3' => 'text-h3 font-semibold'])
<{{ $as ?? 'h2' }}{{ \App\View\Html::attrs(['id' => $id ?? null, 'class' => \App\View\Html::cx('font-display text-heading', $hSizes[$size ?? 'h2'], $class ?? null)]) }}>{{ $text }}@if (! empty($accent)) <em class="heading-accent">{{ $accent }}</em>@endif</{{ $as ?? 'h2' }}>

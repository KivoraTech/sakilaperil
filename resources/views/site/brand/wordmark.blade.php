{{-- Wordmark: "Sakila per il Nepal" → big "Sakila" + small caps "per il Nepal". --}}
@php($wmParts = explode(' ', $name))
@php($wmFirst = array_shift($wmParts))
<span class="flex flex-col leading-none">
<span class="font-display text-[1.375rem] font-semibold tracking-tight text-heading">{{ $wmFirst }}</span>
@if (count($wmParts))
<span class="mt-1 text-[0.6875rem] font-semibold tracking-[0.18em] text-fg-muted uppercase">{{ implode(' ', $wmParts) }}</span>
@endif
</span>

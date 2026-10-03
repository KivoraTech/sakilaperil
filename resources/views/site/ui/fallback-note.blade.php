{{-- components/site/ui/notes.tsx FallbackNote: "Only available in Italian" (ADR-007). Params: $show, $text. --}}
@if (! empty($show))
<p class="inline-flex items-center gap-2 rounded-md bg-info-bg px-3 py-2 text-small text-info">{!! \App\View\Icon::svg('Languages', 'size-4 shrink-0') !!}{{ $text }}</p>
@endif

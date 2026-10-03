{{-- components/site/ui/notes.tsx FallbackNote: "Only available in Italian" note (ADR-007). Param: $show. --}}
@if ($show)<p class="inline-flex items-center gap-2 rounded-md bg-info-bg px-3 py-2 text-small text-info">{!! \App\View\Icon::svg('Languages', 'size-4 shrink-0') !!}{{ t('common.italianOnly') }}</p>@endif

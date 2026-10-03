{{--
    components/site/ui/rail.tsx: horizontal card rail — native overflow scroll + snap, prev/next buttons
    (resources/js/site/rail.ts), focusable labelled region. Params: $label, $class, $slot (HtmlString).
--}}
@php($railBtn = 'flex size-12 items-center justify-center rounded-full bg-white text-himalaya shadow-card ring-1 ring-line transition hover:bg-himalaya hover:text-white disabled:pointer-events-none disabled:opacity-40')
<div{!! ! empty($class) ? ' class="'.e($class).'"' : '' !!} data-rail>
<div class="mb-6 hidden justify-end gap-2 md:flex">
<button type="button" class="{{ $railBtn }}" disabled="" data-rail-prev>{!! \App\View\Icon::svg('ArrowLeft', 'size-5') !!}<span class="sr-only">{{ t('a11y.previous') }}</span></button>
<button type="button" class="{{ $railBtn }}" data-rail-next>{!! \App\View\Icon::svg('ArrowRight', 'size-5') !!}<span class="sr-only">{{ t('a11y.next') }}</span></button>
</div>
<div role="region" aria-label="{{ $label }}" tabindex="0" class="rail -mx-gutter flex [scroll-padding-inline:var(--spacing-gutter)] gap-5 overflow-x-auto px-gutter pb-6 md:gap-6">{{ $slot }}</div>
</div>

{{--
    Draft preview (port of app/preview/[locale]/pages/[pageId]/page.tsx). Session-gated, noindex,
    no-store. Same site chrome as the public pages (layouts.site); the blocks are rendered by the
    PUBLIC renderer (\App\View\Blocks::render, isPreview): invalid sections show a warning box and
    every block is wrapped in a `data-section-id` marker. In embed mode the bridge
    (resources/js/preview-bridge.ts) swaps the contents of #main when the editor asks for a refresh.
--}}
@extends('layouts.site')

@push('head')
    @vite('resources/js/preview-bridge.ts')
@endpush

@if (! $embed)
    @push('body-start')
        <div role="region" aria-label="Preview notice" data-testid="preview-banner" class="sticky top-0 z-[100] border-b-4 border-marigold bg-deep text-white shadow-lg">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm">
                <strong class="rounded bg-marigold px-2 py-0.5 text-xs font-bold tracking-widest text-deep uppercase">ANTEPRIMA / PREVIEW — NOT LIVE</strong>
                <span class="min-w-0 flex-1 truncate">{{ $title }} · {{ \App\I18n\Locales::LABELS[$locale] }} · {{ $liveState }}@if ($hiddenCount) · {{ $hiddenCount }} hidden section{{ $hiddenCount === 1 ? '' : 's' }} not shown @endif</span>
                <a href="/admin/pages/{{ $pageId }}?locale={{ $locale }}" class="rounded px-2 py-0.5 font-semibold underline underline-offset-4 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white">Back to editor</a>
            </div>
        </div>
    @endpush
@endif

@section('content')
    <div id="preview-content" data-preview-stamp="{{ $stamp }}" data-preview-staged="{{ $staged ? '1' : '0' }}" @if ($embed) data-preview-embed="" @endif style="display:contents">
        {{ \App\View\Blocks::render($sections, $locale, isPreview: true) }}
    </div>
@endsection

{{--
    components/site/donate/sticky-donate-bar.tsx: mobile-only bar on project pages that appears once the
    summary has scrolled out (resources/js/site/sticky-donate.ts). Params: $watchId, $href, $label, $cta.
--}}
<div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/95 px-4 py-3 backdrop-blur transition-transform duration-300 md:hidden pointer-events-none translate-y-full" aria-hidden="true" data-sticky-donate="{{ $watchId }}">
<div class="flex items-center gap-3">
<p class="min-w-0 flex-1 truncate text-small font-semibold text-himalaya">{{ $label }}</p>
<a tabindex="-1" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full bg-sindoor px-5 font-semibold text-white shadow-cta" href="{{ lurl($href) }}">{!! \App\View\Icon::svg('Heart', 'size-4') !!}{{ $cta }}</a>
</div>
</div>

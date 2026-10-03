{{-- components/site/ui/notes.tsx EmptyState. Params: $text, $action (HtmlString|null). --}}
<div class="rounded-xl bg-cream px-6 py-12 text-center">
<p class="mx-auto max-w-md text-lead text-fg-muted">{{ $text }}</p>
@if (! empty($action))
<div class="mt-6 flex justify-center">{{ $action }}</div>
@endif
</div>

{{--
    components/site/ui/pagination.tsx: crawlable ?page= pagination with 44px targets and aria-current.
    Params: $page, $pageCount, $pathname, $query (array), $labels [nav, prev, next]. Page label: t('pagination.page').
--}}
@if ($pageCount > 1)
@php($pgHref = fn (int $p) => \App\View\Ui::href($pathname, [...array_filter($query ?? [], fn ($v) => $v !== null), 'page' => $p > 1 ? (string) $p : null]))
@php($pgPages = array_values(array_filter(range(1, $pageCount), fn ($p) => $p === 1 || $p === $pageCount || abs($p - $page) <= 1)))
@php($pgItem = 'flex min-h-11 min-w-11 items-center justify-center rounded-full px-3 font-semibold')
<nav aria-label="{{ $labels['nav'] }}" class="mt-14 flex justify-center">
<ul class="flex flex-wrap items-center gap-1">
<li>@if ($page > 1)<a class="{{ $pgItem }} gap-1 text-himalaya hover:bg-cream" rel="prev" href="{{ $pgHref($page - 1) }}">{!! \App\View\Icon::svg('ChevronLeft', 'size-5') !!}{{ $labels['prev'] }}</a>@endif</li>
@foreach ($pgPages as $pgI => $p)
<li class="flex items-center">
@if ($pgI > 0 && $p - $pgPages[$pgI - 1] > 1)
<span class="px-1 text-fg-muted" aria-hidden="true">…</span>
@endif
<a{!! $p === $page ? ' aria-current="page"' : '' !!} aria-label="{{ t('pagination.page', ['n' => $p]) }}" class="{{ \App\View\Html::cx($pgItem, $p === $page ? 'bg-himalaya text-white' : 'text-himalaya hover:bg-cream') }}" href="{{ $pgHref($p) }}">{{ $p }}</a>
</li>
@endforeach
<li>@if ($page < $pageCount)<a class="{{ $pgItem }} gap-1 text-himalaya hover:bg-cream" rel="next" href="{{ $pgHref($page + 1) }}">{{ $labels['next'] }}{!! \App\View\Icon::svg('ChevronRight', 'size-5') !!}</a>@endif</li>
</ul>
</nav>
@endif

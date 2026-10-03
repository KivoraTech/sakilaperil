{{-- header-nav.tsx ItemLink. Params: $item (NavLink), $pathname, $class, $newTab. --}}
@if ($item['external'])
<a href="{{ $item['href'] }}" class="{{ $class }}"@if ($item['openInNewTab']) target="_blank"@endif rel="noopener noreferrer">{{ $item['label'] }}@if ($item['openInNewTab'])<span class="sr-only"> {{ $newTab }}</span>@endif</a>
@else
@php($nilActive = \App\View\Nav::isActive($pathname, $item['href']))
<a class="{{ $class }}"@if ($nilActive) aria-current="page" data-active="true"@endif href="{{ lurl($item['href']) }}">{{ $item['label'] }}</a>
@endif

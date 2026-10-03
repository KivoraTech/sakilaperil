{{-- site-footer.tsx FooterLinks. Params: $title, $items, $newTab. --}}
@if (count($items))
<div>
<h2 class="text-kicker font-semibold tracking-[0.16em] text-gold uppercase">{{ $title }}</h2>
<ul class="mt-4 space-y-1">
@foreach ($items as $fi)
<li>
@if ($fi['external'])
<a href="{{ $fi['href'] }}" class="inline-flex min-h-11 items-center text-fg hover:text-white hover:underline"@if ($fi['openInNewTab']) target="_blank"@endif rel="noopener noreferrer">{{ $fi['label'] }}@if ($fi['openInNewTab'])<span class="sr-only"> {{ $newTab }}</span>@endif</a>
@else
<a class="inline-flex min-h-11 items-center text-fg underline-offset-4 hover:text-white hover:underline" href="{{ lurl($fi['href']) }}">{{ $fi['label'] }}</a>
@endif
</li>
@endforeach
</ul>
</div>
@endif

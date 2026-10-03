{{-- components/site/ui/share-links.tsx: plain share URLs (no SDKs) + copy-link button. Params: $url, $title. --}}
@php($shU = rawurlencode($url))
@php($shT = rawurlencode($title))
@php($shLinks = [['href' => "https://wa.me/?text={$shT}%20{$shU}", 'label' => 'WhatsApp', 'icon' => \App\View\Icon::svg('MessageCircle', 'size-5')], ['href' => "https://www.facebook.com/sharer/sharer.php?u={$shU}", 'label' => 'Facebook', 'icon' => view('site.brand.social-icon', ['platform' => 'facebook', 'class' => 'size-5'])->render()], ['href' => "mailto:?subject={$shT}&body={$shU}", 'label' => t('share.email'), 'icon' => \App\View\Icon::svg('Mail', 'size-5')]])
<div class="flex flex-wrap items-center gap-2">
<span class="mr-1 text-small font-semibold text-fg-muted">{{ t('share.label') }}</span>
@foreach ($shLinks as $l)
<a href="{{ $l['href'] }}"@if (! str_starts_with($l['href'], 'mailto:')) target="_blank"@endif rel="noopener noreferrer" class="flex size-11 items-center justify-center rounded-full bg-white text-himalaya ring-1 ring-line hover:bg-himalaya hover:text-white">{!! $l['icon'] !!}<span class="sr-only">{{ t('share.via', ['network' => $l['label']]) }}</span></a>
@endforeach
<button type="button" class="flex size-11 items-center justify-center rounded-full bg-white text-himalaya ring-1 ring-line hover:bg-himalaya hover:text-white" data-copy-link="{{ $url }}">{!! \App\View\Icon::svg('Link2', 'size-5') !!}<span class="sr-only">{{ t('share.copy') }}</span><span class="sr-only" aria-live="polite" data-copied-label="{{ t('share.copied') }}"></span><template data-icon-copied>{!! \App\View\Icon::svg('Check', 'size-5') !!}</template></button>
</div>

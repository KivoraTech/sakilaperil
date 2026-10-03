{{--
    Public HTML document (port of app/[locale]/layout.tsx + Next's metadata output).
    Pages pass SEO as `$meta` (App\View\Seo::build()): title, description, robots, canonical,
    alternates [hreflang => url], og [[property, content]…], twitter [[name, content]…].
    Priority images (App\View\Img, `priority`) add their <link rel="preload"> automatically.
--}}
<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $meta['title'] ?? config('app.name') }}</title>
@if (isset($meta['description']))
<meta name="description" content="{{ $meta['description'] }}">
@endif
@if (! empty($meta['robots']))
<meta name="robots" content="{{ $meta['robots'] }}">
@endif
@if (! empty($meta['canonical']))
<link rel="canonical" href="{{ $meta['canonical'] }}">
@endif
@foreach ($meta['alternates'] ?? [] as $hreflang => $href)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
@foreach ($meta['og'] ?? [] as $property => $content)
@if (is_array($content))
<meta property="{{ $content[0] }}" content="{{ $content[1] }}">
@else
<meta property="{{ $property }}" content="{{ $content }}">
@endif
@endforeach
@foreach ($meta['twitter'] ?? [] as $name => $content)
@if (is_array($content))
<meta name="{{ $content[0] }}" content="{{ $content[1] }}">
@else
<meta name="{{ $name }}" content="{{ $content }}">
@endif
@endforeach
{{-- Static icons first (served by the web server, no PHP); the icon chosen in Settings → Look & feel overrides them. --}}
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="{{ $meta['icon'] ?? \App\View\Brand::images(app(\App\Settings\SiteSettings::class)->get())['icon'] }}" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
@include('partials.font-preload')
{{ \App\View\Img::preloadLinks() }}
@vite(['resources/css/site.css', 'resources/js/site.ts'])
@stack('head')
{{ \App\View\Analytics::headTags(app(\App\Settings\SiteSettings::class)->get()) }}
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
@stack('body-start')
@yield('document')
{!! trim($__env->yieldPushContent('body-end')) !!}
</body>
</html>

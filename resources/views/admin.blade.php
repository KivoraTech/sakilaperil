{{-- Inertia root for the CMS (port of app/admin/layout.tsx). The CMS UI is English. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/icon.png" type="image/png">
    @include('partials.font-preload')
    @viteReactRefresh
    @vite(['resources/js/admin.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-muted/40 font-sans antialiased">
    @inertia
</body>
</html>

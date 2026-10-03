{{-- One-time install page (InstallController). Admin look (shadcn tokens from app.css). English, like the CMS. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install · Sakila CMS</title>
@include('partials.font-preload')
@vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-muted/40 p-4 font-sans antialiased">
<main class="w-full max-w-md rounded-xl border bg-background p-6 shadow-sm">
<div class="flex items-center gap-2">
<img src="/logo.png" alt="" width="32" height="32">
<span class="font-serif text-base font-semibold text-himalaya">Sakila <span class="block text-xs font-normal text-muted-foreground">CMS</span></span>
</div>
<h1 class="mt-6 text-xl font-semibold">Create the first administrator</h1>
<p class="mt-1 text-sm text-muted-foreground">This page works only once. Afterwards, add people in Admin → Users and remove <code>INSTALL_TOKEN</code> from <code>.env</code>.</p>
@if (isset($installErrors['form']))
<p role="alert" class="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ $installErrors['form'] }}</p>
@endif
<form method="post" action="/install" class="mt-6 grid gap-4" novalidate>
@csrf
@foreach ([['token', 'Install token (from .env)', 'password', 'off'], ['name', 'Your name', 'text', 'name'], ['email', 'Email', 'email', 'email'], ['password', 'Password (12+ characters)', 'password', 'new-password']] as [$field, $label, $type, $ac])
<div class="grid gap-1.5">
<label for="install-{{ $field }}" class="text-sm font-medium">{{ $label }}</label>
<input id="install-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $ac }}" required value="{{ in_array($field, ['name', 'email'], true) ? ($old[$field] ?? '') : '' }}" @if (isset($installErrors[$field])) aria-invalid="true" aria-describedby="install-{{ $field }}-error" @endif class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive">
@if (isset($installErrors[$field]))<p id="install-{{ $field }}-error" class="text-sm text-destructive">{{ $installErrors[$field] }}</p>@endif
</div>
@endforeach
<button type="submit" class="mt-2 inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">Create administrator</button>
</form>
</main>
</body>
</html>

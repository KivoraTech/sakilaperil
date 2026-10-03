{{-- Self-contained error page (no database, no Vite): used when the site chrome itself can't
     render (e.g. the database is down) and for plain 404s outside /{it|en}. Bilingual. --}}
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('code') · Sakila per il Nepal</title>
<style>
:root{color-scheme:light}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fbf7f0;color:#1f2328;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:34rem;padding:2rem 1.25rem;text-align:center}
.code{font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#b5452f;font-size:.8rem}
h1{font-family:Georgia,"Times New Roman",serif;font-size:2rem;line-height:1.2;color:#1e3a5f;margin:.75rem 0}
p{color:#5a5f66;margin:.5rem 0}
a{display:inline-block;margin-top:1.5rem;padding:.75rem 1.5rem;border-radius:999px;background:#b5452f;color:#fff;font-weight:600;text-decoration:none}
a:focus-visible{outline:3px solid #1e3a5f;outline-offset:3px}
</style>
</head>
<body>
<main role="alert">
<p class="code">@yield('code')</p>
<h1>@yield('title_it')</h1>
<p>@yield('body_it')</p>
<p lang="en">@yield('body_en')</p>
<a href="/">Torna alla home · Home</a>
</main>
</body>
</html>

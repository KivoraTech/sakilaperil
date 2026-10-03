<?php

declare(strict_types=1);

/*
 * Cross-origin requests (ENGAGEMENT, audit SEC-07). The public JSON API (/api/forms, /api/newsletter,
 * /api/donations) is called only by this site's own pages, so no path opts in to CORS: without this
 * file Laravel's default answers every /api/* preflight with `Access-Control-Allow-Origin: *`.
 * Webhooks are server-to-server (no browser, no CORS). Public POSTs additionally require a
 * same-origin `Origin` header (App\Engagement\Http\PublicJson::isSameOrigin).
 */
return [
    'paths' => [],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => [],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];

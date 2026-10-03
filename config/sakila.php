<?php

declare(strict_types=1);

/** App-specific settings (env → config, so `php artisan config:cache` works on cPanel). */
return [
    /**
     * Public site URL (absolute links, sitemap, emails, payment returns). SITE_URL, else APP_URL.
     * There is deliberately no localhost fallback in production: an unset value is reported by
     * App\Support\ProductionConfig (503 + log + `php artisan sakila:config-check`).
     */
    'site_url' => env('SITE_URL') ?: (env('APP_URL') ?: (env('APP_ENV', 'production') === 'production' ? '' : 'http://localhost:8000')),

    // One-time /install page (first super admin, no SSH needed). Set 16+ random characters only
    // while installing, then remove it. The page is also disabled once a super admin exists.
    'install_token' => env('INSTALL_TOKEN', ''),

    /**
     * Reverse proxy (Cloudflare). Forwarded headers are honoured only when TRUST_PROXY=true AND the
     * connecting address is one of TRUSTED_PROXIES:
     *   "cloudflare" (default) = Cloudflare's published ranges below,
     *   a comma-separated list of IPs / CIDR ranges,
     *   "*" = whoever connects (only if the origin is firewalled to the proxy; otherwise anyone can
     *         forge their IP and bypass the per-IP limits).
     * The client IP is the right-most forwarded address that is not a trusted proxy, never the
     * left-most one (which the client controls). X-Forwarded-Host is never trusted.
     */
    'trust_proxy' => env('TRUST_PROXY', false),
    'trusted_proxies' => env('TRUSTED_PROXIES', 'cloudflare'),
    // https://www.cloudflare.com/ips/ (checked 2026-10). Update here if Cloudflare adds ranges.
    'cloudflare_ranges' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ],

    /**
     * Data retention, enforced daily by `sakila:housekeeping` (0 = keep forever).
     * Audit log: IP address and browser of staff actions are blanked after `audit_ip_days`
     * (they are only needed to investigate a recent incident); whole entries go after `audit_months`.
     */
    'retention' => [
        'audit_ip_days' => (int) env('AUDIT_IP_RETENTION_DAYS', 90),
        'audit_months' => (int) env('AUDIT_RETENTION_MONTHS', 24),
    ],

    /** Resized image variants (regenerated on demand). Housekeeping keeps the folder under this size. */
    'img_cache_dir' => env('IMG_CACHE_DIR') ?: storage_path('app/img-cache'),
    'img_cache_max_mb' => (int) env('IMG_CACHE_MAX_MB', 300),

    /** Public read cache (TagCache). Disable only for debugging. */
    'public_cache' => env('PUBLIC_CACHE', true),

    /** Uploads: local (cPanel) or s3 (R2/S3/MinIO). */
    'storage_driver' => env('STORAGE_DRIVER', 'local'),
    'local_uploads_dir' => env('LOCAL_UPLOADS_DIR', ''),
    'upload_max_image_mb' => env('UPLOAD_MAX_IMAGE_MB', 10),
    'upload_max_document_mb' => env('UPLOAD_MAX_DOCUMENT_MB', 25),
    's3' => [
        'endpoint' => env('S3_ENDPOINT', ''),
        'region' => env('S3_REGION', 'auto'),
        'bucket' => env('S3_BUCKET', ''),
        'access_key_id' => env('S3_ACCESS_KEY_ID', ''),
        'secret_access_key' => env('S3_SECRET_ACCESS_KEY', ''),
        'public_url' => env('S3_PUBLIC_URL', ''),
    ],

    /** Seeded accounts (change after first login). */
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@sakila.local'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'ChangeMe-Sakila-2026!'),
        'admin_name' => env('SEED_ADMIN_NAME', 'Sakila Admin'),
        'demo_password' => env('SEED_DEMO_PASSWORD', 'ChangeMe-Sakila-2026!'),
        'demo_users' => env('SEED_DEMO_USERS', true),
        'allow_production' => env('SEED_ALLOW_PRODUCTION', false),
    ],
];

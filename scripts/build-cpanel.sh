#!/usr/bin/env bash
# Builds the cPanel upload package on YOUR computer (the server needs only PHP 8.2+ and MySQL):
#
#   scripts/build-cpanel.sh [version]        → dist/sakila-cpanel-<version>.zip + dist/sakila-deploy.sh
#
# Upload both to the cPanel home folder and run sakila-deploy.sh there (docs/deploy-cpanel.md).
# Zip layout:
#   sakila/            the Laravel app (code, vendor/, storage/) — OUTSIDE the web root
#   public_html/       what the web server serves (index.php → ../sakila, build/, logo …)
#   sakila-uploads/    the photos of the site content (first install only)
#   sakila-database.sql   schema + site content, no users (first install only)
#   INSTALL.md         the step-by-step guide
# Needs: php + composer, node + npm, and a MySQL/MariaDB to build the SQL file
# (MYSQL_PORT etc. as in scripts/build-database-sql.sh; skip it with SKIP_SQL=1).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
VERSION="${1:-$(date +%Y.%m.%d-%H%M)}"
DIST="${DIST_DIR:-$ROOT/dist}"   # DIST_DIR: build somewhere else (tests) without touching dist/
STAGE="$DIST/stage"
PKG="$DIST/sakila-cpanel-$VERSION.zip"
# A real PHP 8.2 (the oldest version the hosting may run) for the platform and syntax checks:
# $PHP82, else XAMPP's, else `php` if that is 8.2. REQUIRE_PHP82=1 (CI) makes a missing one fatal.
PHP82="${PHP82:-/Applications/XAMPP/xamppfiles/bin/php}"
if [[ ! -x "$PHP82" ]]; then
  if php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 2 ? 0 : 1);' 2>/dev/null; then PHP82="$(command -v php)"; else PHP82=""; fi
fi
if [[ -z "$PHP82" ]]; then
  [[ "${REQUIRE_PHP82:-0}" == "1" ]] && { echo "✗ No PHP 8.2 found (set PHP82=/path/to/php8.2)" >&2; exit 1; }
  echo "! No PHP 8.2 found: the PHP 8.2 platform and syntax checks are SKIPPED (set PHP82=/path/to/php8.2)." >&2
fi

echo "▸ Building Sakila cPanel package $VERSION"
rm -rf "$STAGE" && mkdir -p "$STAGE"

# 1. Front-end assets (compiled locally; the server never runs Node). Shares the repo build lock.
until mkdir /tmp/sakila-laravel-build.lock 2>/dev/null; do sleep 3; done
trap 'rmdir /tmp/sakila-laravel-build.lock 2>/dev/null || true' EXIT
[[ -d node_modules ]] || npm ci --no-audit --no-fund
if [[ "${SKIP_NPM_BUILD:-0}" == "1" && -f public/build/manifest.json ]]; then
  echo "  (SKIP_NPM_BUILD=1: packaging the existing public/build)"
else
  npm run build >/dev/null
fi
rmdir /tmp/sakila-laravel-build.lock; trap - EXIT

# 2. App files (no dev tooling, tests, sources of compiled assets, secrets or runtime data).
rsync -a "$ROOT/" "$STAGE/sakila/" \
  --exclude '.git/' --exclude '.github/' --exclude 'node_modules/' --exclude 'vendor/' --exclude 'dist/' \
  --exclude 'assets/' --exclude 'database/content/' --exclude 'database/seeders/content/' --exclude 'database/seeders/files/' --exclude 'tests/' --exclude '.nvmrc' --exclude '/*.md' --exclude 'test-results/' --exclude 'playwright-report/' --exclude 'docs/' --exclude 'scripts/' \
  --exclude '.env' --exclude '.env.*.local' --exclude '.env.backup' --exclude '.env.production' --exclude '.env.testing' \
  --exclude 'public/hot' --exclude 'public/storage' --exclude 'storage/***' --exclude 'bootstrap/cache/*.php' \
  --exclude 'resources/js/***' --exclude 'resources/css/' --exclude 'resources/fonts/' \
  --exclude 'package.json' --exclude 'package-lock.json' --exclude 'vite.config.ts' --exclude 'tsconfig*.json' \
  --exclude 'eslint.config.*' --exclude '.prettier*' --exclude 'playwright.config.ts' --exclude 'phpunit.xml' \
  --exclude 'phpstan.neon' --exclude 'pint.json' --exclude '.editorconfig' --exclude '.phpunit*' --exclude 'database/database.sqlite' \
  --exclude '*.log' --exclude '.DS_Store' --exclude '.kilo/' --exclude '.idea/' --exclude '.vscode/'
# The only runtime file under resources/js (the engagement icon map, read by PHP).
mkdir -p "$STAGE/sakila/resources/js/site/engagement"
cp resources/js/site/engagement/icons.json "$STAGE/sakila/resources/js/site/engagement/icons.json"

# Writable runtime folders (empty).
for d in storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache; do
  mkdir -p "$STAGE/sakila/$d"; : > "$STAGE/sakila/$d/.gitkeep"
done

# 3. PHP dependencies for production, locked to the PHP 8.2 platform (composer.json config.platform).
( cd "$STAGE/sakila" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet )
if [[ -n "$PHP82" ]]; then
  ( cd "$STAGE/sakila" && "$PHP82" "$(command -v composer)" check-platform-reqs --no-dev --quiet ) \
    || { echo "✗ A production dependency needs more than PHP 8.2" >&2; exit 1; }
  # Syntax-check every shipped PHP file with real PHP 8.2 (catches 8.3+ syntax).
  ( cd "$STAGE/sakila" && find app bootstrap config database routes public -name '*.php' -print0 | xargs -0 -n1 -P8 "$PHP82" -l >/dev/null ) \
    || { echo "✗ PHP 8.2 syntax check failed" >&2; exit 1; }
fi
# Route cache is path-independent (config/view caches are not: they're built on the server, if at all).
( cd "$STAGE/sakila" && php artisan route:cache --quiet )

# 4. public_html = the public folder + a front controller that points to ../sakila.
mkdir -p "$STAGE/public_html"
rsync -a "$STAGE/sakila/public/" "$STAGE/public_html/"
cp scripts/cpanel/public_html-index.php "$STAGE/public_html/index.php"

# 5. First-install extras: database + photos of the site content, the guide.
if [[ "${SKIP_SQL:-0}" != "1" ]]; then
  UPLOADS_OUT="$STAGE/sakila-uploads" scripts/build-database-sql.sh "$STAGE/sakila-database.sql"
fi
# The guide is a local document (markdown files are not in the repository): include it when present.
[[ -f docs/deploy-cpanel.md ]] && cp docs/deploy-cpanel.md "$STAGE/INSTALL.md"
printf '%s\n' "$VERSION" > "$STAGE/sakila/VERSION"

# 6. Zip (folders at the top level).
rm -f "$PKG"
( cd "$STAGE" && zip -qr -X "$PKG" sakila public_html $( [[ -f INSTALL.md ]] && echo INSTALL.md ) $( [[ -f sakila-database.sql ]] && echo sakila-database.sql ) $( [[ -d sakila-uploads ]] && echo sakila-uploads ) )
[[ -f "$STAGE/sakila-database.sql" ]] && cp "$STAGE/sakila-database.sql" "$DIST/sakila-database.sql"
# The installer/updater that runs ON the server (uploaded next to the zip; see docs/deploy-cpanel.md).
cp scripts/cpanel/sakila-deploy.sh "$DIST/sakila-deploy.sh"
rm -rf "$STAGE"
echo "✓ $PKG ($(du -h "$PKG" | cut -f1)) + $DIST/sakila-deploy.sh"
